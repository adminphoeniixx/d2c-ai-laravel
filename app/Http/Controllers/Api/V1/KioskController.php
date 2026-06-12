<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Msg91Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | KIOSK APIs — Shared tablet attendance device
    |--------------------------------------------------------------------------
    |
    | 1. Company login via admin phone+OTP
    | 2. Get employee list (with face encodings for local matching)
    | 3. Save face encoding for an employee
    | 4. Punch in  — dev sends employee_id + time
    | 5. Punch out — dev sends employee_id + time
    |
    | Multiple punch in/out per day allowed (breaks, going out).
    | First punch-in of the day creates the attendance record.
    | All punches logged in the `punches` table.
    |
    */

    // ═══════════════════════════════════════════
    // 1. COMPANY LOGIN
    // ═══════════════════════════════════════════

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'min:10']]);

        $phone = $this->cleanPhone($request->phone);

        $user = DB::table('users')
            ->where(function ($q) use ($phone) {
                $q->where('phone', $phone)
                  ->orWhere('phone', '+91' . $phone)
                  ->orWhere('phone', '91' . $phone)
                  ->orWhere('phone', 'LIKE', '%' . $phone);
            })
            ->whereNotNull('company_id')
            ->first();

        if (!$user) {
            return response()->json(['error' => 'No admin account found with this phone number'], 404);
        }

        $company = DB::table('companies')->where('id', $user->company_id)->first();
        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put("kiosk_otp:{$phone}", [
            'otp'        => $otp,
            'user_id'    => $user->id,
            'user_name'  => $user->name,
            'company_id' => $company->id,
            'company'    => $company->name,
            'schema'     => 'tenant_' . $company->id,
        ], 300);

        $smsSent = false;
        try {
            $smsSent = (new Msg91Service())->sendOtp($phone, $otp);
        } catch (\Throwable $e) {
            Log::error('Kiosk OTP failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success'      => true,
            'message'      => $smsSent ? 'OTP sent' : 'OTP generated (SMS failed)',
            'sms_sent'     => $smsSent,
            'company_name' => $company->name,
            'admin_name'   => $user->name,
            'otp_debug'    => app()->environment('local', 'staging') ? $otp : null,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $phone = $this->cleanPhone($request->phone);

        $cached = Cache::get("kiosk_otp:{$phone}");
        if (!$cached || $cached['otp'] !== $request->otp) {
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }

        Cache::forget("kiosk_otp:{$phone}");

        $token = 'kiosk_' . Str::random(60);
        Cache::put("kiosk_token:{$token}", [
            'user_id'    => $cached['user_id'],
            'company_id' => $cached['company_id'],
            'schema'     => $cached['schema'],
        ], 86400 * 90); // 90 days

        return response()->json([
            'success'      => true,
            'token'        => $token,
            'kiosk'        => [
                'id'   => crc32($token), // deterministic ID from token
                'name' => 'Kiosk - ' . $cached['company'],
            ],
            'kiosk_token'  => $token, // backward compat
            'company_name' => $cached['company'],
            'admin_name'   => $cached['user_name'],
        ]);
    }

    // ═══════════════════════════════════════════
    // 2. EMPLOYEE LIST (with face data)
    // ═══════════════════════════════════════════

    /**
     * GET /api/v1/kiosk/employees
     *
     * Returns all active employees with face_encoding.
     * App caches face data locally and does matching on-device.
     */
    public function employees(Request $request): JsonResponse
    {
        $auth = $this->resolveKiosk($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $employees = DB::connection('tenant')->table('employees')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'employee_id', 'first_name', 'last_name', 'designation',
                    'department', 'phone', 'face_encoding']);

        $list = $employees->map(fn ($emp) => [
            'id'            => $emp->id,
            'employee_id'   => $emp->employee_id,
            'name'          => trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')),
            'designation'   => $emp->designation,
            'department'    => $emp->department,
            'has_face'      => !empty($emp->face_encoding),
            'face_encoding' => $emp->face_encoding, // app uses this for local matching
        ]);

        return response()->json([
            'total'      => $list->count(),
            'registered' => $list->where('has_face', true)->count(),
            'employees'  => $list->values(),
        ]);
    }

    // ═══════════════════════════════════════════
    // 3. SAVE FACE
    // ═══════════════════════════════════════════

    /**
     * POST /api/v1/kiosk/employees/{id}/face
     * Body: { face_encoding }
     */
    public function saveFace(Request $request, string $id): JsonResponse
    {
        $auth = $this->resolveKiosk($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate(['face_encoding' => ['required', 'string']]);

        $employee = DB::connection('tenant')->table('employees')
            ->where('id', $id)->where('status', 'active')->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        DB::connection('tenant')->table('employees')
            ->where('id', $id)
            ->update([
                'face_encoding'      => $request->face_encoding,
                'has_face'           => true,
                'face_registered_at' => now(),
                'face_updated_at'    => now(),
                'updated_at'         => now(),
            ]);

        $name = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));

        return response()->json([
            'success'  => true,
            'message'  => "Face saved for {$name}",
            'has_face' => true,
            'face_registered_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/v1/kiosk/employees/{id}/face
     */
    public function removeFace(Request $request, string $id): JsonResponse
    {
        $auth = $this->resolveKiosk($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        DB::connection('tenant')->table('employees')
            ->where('id', $id)
            ->update([
                'face_encoding'      => null,
                'has_face'           => false,
                'face_registered_at' => null,
                'face_updated_at'    => null,
                'updated_at'         => now(),
            ]);

        return response()->json(['success' => true, 'message' => 'Face removed']);
    }

    // ═══════════════════════════════════════════
    // 4. PUNCH IN
    // ═══════════════════════════════════════════

    /**
     * POST /api/v1/kiosk/punch-in
     * Body: { employee_id, time }
     *
     * - Logs a punch-in record in `punches` table
     * - First punch-in of the day creates/updates `attendances` record
     * - Can punch in multiple times (after a punch-out)
     */
    public function punchIn(Request $request): JsonResponse
    {
        $auth = $this->resolveKiosk($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate([
            'employee_id'  => ['required', 'integer'],
            'time'         => ['required', 'date_format:H:i:s'],
            'client_log_id'=> ['nullable', 'string', 'max:60'],
        ]);

        // Offline dedup: if client_log_id already exists, return success (idempotent)
        if ($request->filled('client_log_id')) {
            $existing = DB::connection('tenant')->table('punches')
                ->where('client_log_id', $request->client_log_id)->first();
            if ($existing) {
                return response()->json([
                    'success' => true, 'type' => 'punch_in', 'duplicate' => true,
                    'message' => 'Already recorded (duplicate client_log_id)',
                ]);
            }
        }

        $employee = DB::connection('tenant')->table('employees')
            ->where('id', $request->employee_id)->where('status', 'active')->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $today = now()->toDateString();
        $name = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));

        // Check last punch — must be 'out' or no punch yet
        $lastPunch = DB::connection('tenant')->table('punches')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->orderByDesc('id')
            ->first();

        if ($lastPunch && $lastPunch->type === 'in') {
            return response()->json([
                'error' => "{$name} already punched in at {$lastPunch->time}. Must punch out first.",
            ], 422);
        }

        // Log punch
        DB::connection('tenant')->table('punches')->insert([
            'employee_id'  => $employee->id,
            'date'         => $today,
            'type'         => 'in',
            'time'         => $request->time,
            'source'       => 'kiosk',
            'client_log_id'=> $request->input('client_log_id'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // First punch-in of the day → create attendance record
        $attendance = DB::connection('tenant')->table('attendances')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            // Calculate late
            $lateMinutes = 0;
            $isLate = false;
            $penaltyAmount = 0;

            try {
                $settings = DB::connection('tenant')->table('attendance_settings')->first();
                if ($settings) {
                    $shiftStart = $settings->shift_start_time ?? '09:00:00';
                    $graceMinutes = $settings->grace_period_minutes ?? 15;
                    $shiftTime = \Carbon\Carbon::parse($today . ' ' . $shiftStart);
                    $graceEnd = $shiftTime->copy()->addMinutes($graceMinutes);
                    $punchTime = \Carbon\Carbon::parse($today . ' ' . $request->time);

                    if ($punchTime->isAfter($graceEnd)) {
                        $lateMinutes = (int) $shiftTime->diffInMinutes($punchTime);
                        $isLate = true;
                        if ($settings->late_penalty_enabled ?? false) {
                            $penaltyAmount = (float) ($settings->late_penalty_amount ?? 0);
                        }
                    }
                }
            } catch (\Throwable $e) {}

            DB::connection('tenant')->table('attendances')->insert([
                'employee_id'   => $employee->id,
                'date'          => $today,
                'check_in'      => $request->time,
                'status'        => 'present',
                'late_minutes'  => $lateMinutes,
                'is_late'       => $isLate,
                'penalty_amount'=> $penaltyAmount,
                'face_verified' => true,
                'source'        => 'kiosk',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Count today's punches
        $punchCount = DB::connection('tenant')->table('punches')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->count();

        return response()->json([
            'success'     => true,
            'type'        => 'punch_in',
            'message'     => "{$name} punched in at {$request->time}",
            'employee_id' => $employee->id,
            'name'        => $name,
            'time'        => $request->time,
            'is_first'    => !$lastPunch, // first punch of the day
            'is_late'     => $isLate ?? false,
            'late_minutes'=> $lateMinutes ?? 0,
            'punch_count' => $punchCount,
        ]);
    }

    // ═══════════════════════════════════════════
    // 5. PUNCH OUT
    // ═══════════════════════════════════════════

    /**
     * POST /api/v1/kiosk/punch-out
     * Body: { employee_id, time }
     *
     * - Must have an open punch-in first
     * - Logs punch-out in `punches` table
     * - Updates attendance with latest check-out time and worked hours
     */
    public function punchOut(Request $request): JsonResponse
    {
        $auth = $this->resolveKiosk($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate([
            'employee_id'  => ['required', 'integer'],
            'time'         => ['required', 'date_format:H:i:s'],
            'client_log_id'=> ['nullable', 'string', 'max:60'],
        ]);

        // Offline dedup
        if ($request->filled('client_log_id')) {
            $existing = DB::connection('tenant')->table('punches')
                ->where('client_log_id', $request->client_log_id)->first();
            if ($existing) {
                return response()->json([
                    'success' => true, 'type' => 'punch_out', 'duplicate' => true,
                    'message' => 'Already recorded (duplicate client_log_id)',
                ]);
            }
        }

        $employee = DB::connection('tenant')->table('employees')
            ->where('id', $request->employee_id)->where('status', 'active')->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $today = now()->toDateString();
        $name = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));

        // Must have punched in first
        $lastPunch = DB::connection('tenant')->table('punches')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->orderByDesc('id')
            ->first();

        if (!$lastPunch || $lastPunch->type === 'out') {
            return response()->json([
                'error' => "{$name} hasn't punched in yet. Must punch in first.",
            ], 422);
        }

        // Log punch out
        DB::connection('tenant')->table('punches')->insert([
            'employee_id'  => $employee->id,
            'date'         => $today,
            'type'         => 'out',
            'time'         => $request->time,
            'source'       => 'kiosk',
            'client_log_id'=> $request->input('client_log_id'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Calculate total worked hours from all punch pairs today
        $allPunches = DB::connection('tenant')->table('punches')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->orderBy('id')
            ->get();

        $totalMinutes = 0;
        $ins = [];
        foreach ($allPunches as $p) {
            if ($p->type === 'in') {
                $ins[] = $p->time;
            } elseif ($p->type === 'out' && !empty($ins)) {
                $inTime = \Carbon\Carbon::parse($today . ' ' . array_pop($ins));
                $outTime = \Carbon\Carbon::parse($today . ' ' . $p->time);
                $totalMinutes += $inTime->diffInMinutes($outTime);
            }
        }

        $workedHours = round($totalMinutes / 60, 2);

        // Calculate overtime
        $overtimeHours = 0;
        try {
            $settings = DB::connection('tenant')->table('attendance_settings')->first();
            if ($settings) {
                $standardHours = (float) ($settings->standard_hours ?? 8);
                $otMinMinutes = (int) ($settings->overtime_min_minutes ?? 30);
                $extraMinutes = $totalMinutes - ($standardHours * 60);
                if ($extraMinutes >= $otMinMinutes) {
                    $overtimeHours = round($extraMinutes / 60, 2);
                }
            }
        } catch (\Throwable $e) {}

        // Status based on worked hours
        $status = 'present';
        if ($workedHours < 4) $status = 'half_day';

        // Update attendance record with latest checkout and total hours
        $attendance = DB::connection('tenant')->table('attendances')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        if ($attendance) {
            DB::connection('tenant')->table('attendances')
                ->where('id', $attendance->id)
                ->update([
                    'check_out'      => $request->time,
                    'worked_hours'   => $workedHours,
                    'overtime_hours' => $overtimeHours,
                    'status'         => $status,
                    'updated_at'     => now(),
                ]);
        }

        $punchCount = $allPunches->count();

        return response()->json([
            'success'        => true,
            'type'           => 'punch_out',
            'message'        => "{$name} punched out at {$request->time} ({$workedHours} hrs total)",
            'employee_id'    => $employee->id,
            'name'           => $name,
            'time'           => $request->time,
            'worked_hours'   => $workedHours,
            'overtime_hours' => $overtimeHours,
            'status'         => $status,
            'punch_count'    => $punchCount,
        ]);
    }

    // ═══════════════════════════════════════════
    // 6. TODAY'S STATUS
    // ═══════════════════════════════════════════

    /**
     * GET /api/v1/kiosk/today
     *
     * Returns today's attendance + all punches for every employee.
     */
    public function today(Request $request): JsonResponse
    {
        $auth = $this->resolveKiosk($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $today = now()->toDateString();

        $employees = DB::connection('tenant')->table('employees')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'employee_id', 'first_name', 'last_name', 'designation', 'department']);

        $attendances = DB::connection('tenant')->table('attendances')
            ->where('date', $today)->get()->keyBy('employee_id');

        $punches = DB::connection('tenant')->table('punches')
            ->where('date', $today)->orderBy('id')->get()->groupBy('employee_id');

        $list = $employees->map(function ($emp) use ($attendances, $punches) {
            $att = $attendances->get($emp->id);
            $empPunches = $punches->get($emp->id, collect())->map(fn ($p) => [
                'type' => $p->type,
                'time' => $p->time,
            ])->values();

            $lastPunch = $empPunches->last();

            return [
                'id'            => $emp->id,
                'employee_id'   => $emp->employee_id,
                'name'          => trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')),
                'designation'   => $emp->designation,
                'department'    => $emp->department,
                'status'        => $att->status ?? 'not_marked',
                'check_in'      => $att->check_in ?? null,
                'check_out'     => $att->check_out ?? null,
                'is_late'       => (bool) ($att->is_late ?? false),
                'late_minutes'  => (int) ($att->late_minutes ?? 0),
                'worked_hours'  => (float) ($att->worked_hours ?? 0),
                'current_state' => $lastPunch ? ($lastPunch['type'] === 'in' ? 'in' : 'out') : 'not_started',
                'punches'       => $empPunches,
                'punch_count'   => $empPunches->count(),
            ];
        });

        return response()->json([
            'date'    => $today,
            'summary' => [
                'total'       => $list->count(),
                'present'     => $list->where('status', '!=', 'not_marked')->count(),
                'not_marked'  => $list->where('status', 'not_marked')->count(),
                'currently_in'=> $list->where('current_state', 'in')->count(),
                'late'        => $list->where('is_late', true)->count(),
            ],
            'employees' => $list->values(),
        ]);
    }

    // ═══════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════

    protected function cleanPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            $phone = substr($phone, -10);
        }
        return $phone;
    }

    protected function resolveKiosk(Request $request): ?array
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        $data = Cache::get("kiosk_token:{$token}");
        if (!$data) return null;

        DB::connection('tenant')->statement("SET search_path TO \"{$data['schema']}\", public");
        return $data;
    }
}
