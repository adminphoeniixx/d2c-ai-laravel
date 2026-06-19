<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/send-otp
     * Body: { phone }
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:10'],
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            $phone = substr($phone, -10);
        }

        $match = $this->findEmployeeByPhone($phone);

        if (!$match) {
            return response()->json(['error' => 'No active employee found with this phone number'], 404);
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put("otp:{$phone}", [
            'otp'         => $otp,
            'company_id'  => $match['company_id'],
            'employee_id' => $match['employee_id'],
            'schema'      => $match['schema'],
        ], 300);

        // TODO: Send OTP via SMS gateway
        // Send OTP via MSG91
        $smsService = new \App\Services\Msg91Service();
        $smsSent = $smsService->sendOtp($phone, $otp);

        return response()->json([
            'success'       => true,
            'message'       => $smsSent
                ? 'OTP sent to ' . substr($phone, 0, 3) . '****' . substr($phone, -3)
                : 'OTP generated (SMS delivery pending)',
            'sms_sent'      => $smsSent,
            'company_name'  => $match['company_name'],
            'employee_name' => $match['employee_name'],
            'otp_debug'     => app()->environment('local', 'staging') ? $otp : null,
        ]);
    }

    /**
     * POST /api/v1/auth/verify-otp
     * Body: { phone, otp }
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            $phone = substr($phone, -10);
        }

        $cached = Cache::get("otp:{$phone}");
        if (!$cached || $cached['otp'] !== $request->otp) {
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }

        Cache::forget("otp:{$phone}");

        $schema = $cached['schema'];
        DB::connection('tenant')->statement("SET search_path TO \"{$schema}\", public");

        $employee = DB::connection('tenant')->table('employees')
            ->where('id', $cached['employee_id'])
            ->where('status', 'active')
            ->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found or inactive'], 404);
        }

        $company = DB::table('companies')->where('id', $cached['company_id'])->first();

        $token = Str::random(64);
        Cache::put("emp_token:{$token}", [
            'company_id'  => $cached['company_id'],
            'employee_id' => $employee->id,
            'schema'      => $schema,
        ], 86400 * 30);

        $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));

        return response()->json([
            'success'  => true,
            'token'    => $token,
            'employee' => [
                'id'           => $employee->id,
                'employee_id'  => $employee->employee_id ?? null,
                'name'         => $fullName,
                'designation'  => $employee->designation ?? null,
                'department'   => $employee->department ?? null,
                'phone'        => $employee->phone,
                'has_face'     => !empty($employee->face_encoding),
            ],
            'company' => [
                'id'   => $company->id ?? null,
                'name' => $company->name ?? null,
                'slug' => $company->slug ?? null,
                'logo' => $company->letterhead_url ?? null,
            ],
        ]);
    }

    /**
     * GET /api/v1/me
     * Returns employee profile + face encoding for local caching
     */
    public function me(Request $request): JsonResponse
    {
        $auth = self::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $employee = DB::connection('tenant')->table('employees')
            ->where('id', $auth['employee_id'])
            ->first();

        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $company = DB::table('companies')->where('id', $auth['company_id'])->first();

        $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));

        return response()->json([
            'employee' => [
                'id'            => $employee->id,
                'employee_id'   => $employee->employee_id ?? null,
                'name'          => $fullName,
                'first_name'    => $employee->first_name,
                'last_name'     => $employee->last_name,
                'designation'   => $employee->designation ?? null,
                'department'    => $employee->department ?? null,
                'phone'         => $employee->phone,
                'email'         => $employee->email ?? null,
                'has_face'      => !empty($employee->face_encoding),
                'face_encoding' => $employee->face_encoding ?? null,
            ],
            'company' => [
                'id'   => $company->id ?? null,
                'name' => $company->name ?? null,
                'slug' => $company->slug ?? null,
                'logo' => $company->letterhead_url ?? null,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/register-face
     */
    public function registerFace(Request $request): JsonResponse
    {
        $auth = self::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate(['face_encoding' => ['required', 'string']]);

        DB::connection('tenant')->statement("SET search_path TO \"{$auth['schema']}\", public");
        DB::connection('tenant')->table('employees')
            ->where('id', $auth['employee_id'])
            ->update(['face_encoding' => $request->face_encoding]);

        return response()->json(['success' => true, 'message' => 'Face registered successfully']);
    }

    /* ── Registration OTP (for new signups) ── */

    public function registerSendOtp(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'min:10']]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            $phone = substr($phone, -10);
        }

        // Check if phone already registered
        $existing = DB::table('users')->where('phone', $phone)->first();
        if ($existing) {
            return response()->json(['error' => 'This phone number is already registered'], 422);
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put("reg_otp:{$phone}", ['otp' => $otp], 300);

        $smsSent = false;
        try {
            $smsSent = (new \App\Services\Msg91Service())->sendOtp($phone, $otp);
        } catch (\Throwable $e) {
            try {
                Log::error('Registration OTP failed', ['error' => $e->getMessage()]);
            } catch (\Throwable) {
                // Logging itself can fail (e.g. storage permission issues) —
                // never let that mask the original OTP failure.
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => $smsSent ? 'OTP sent' : 'OTP generated (SMS failed)',
            'sms_sent' => $smsSent,
            'otp_debug'=> app()->environment('local', 'staging') ? $otp : null,
        ]);
    }

    public function registerVerifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            $phone = substr($phone, -10);
        }

        $cached = Cache::get("reg_otp:{$phone}");
        if (!$cached || $cached['otp'] !== $request->otp) {
            return response()->json(['error' => 'Invalid or expired OTP'], 401);
        }

        Cache::forget("reg_otp:{$phone}");
        // Mark phone as verified for 30 minutes (registration must complete within this time)
        Cache::put("reg_verified:{$phone}", true, 1800);

        return response()->json(['success' => true, 'message' => 'Phone verified']);
    }

    /* ── Helpers ──────────────────────────────── */

    protected function findEmployeeByPhone(string $phone): ?array
    {
        $companies = DB::table('companies')->get();

        foreach ($companies as $company) {
            $schema = 'tenant_' . $company->id;

            try {
                $exists = DB::select(
                    "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'employees' LIMIT 1",
                    [$schema]
                );

                if (empty($exists)) continue;

                // Use the tenant connection with explicit schema
                DB::connection('tenant')->statement("SET search_path TO \"{$schema}\", public");

                $employee = DB::connection('tenant')->table('employees')
                    ->where('status', 'active')
                    ->where(function ($q) use ($phone) {
                        $q->where('phone', $phone)
                          ->orWhere('phone', '+91' . $phone)
                          ->orWhere('phone', '91' . $phone)
                          ->orWhere('phone', 'LIKE', '%' . $phone);
                    })
                    ->first();

                if ($employee) {
                    $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
                    return [
                        'company_id'    => $company->id,
                        'company_name'  => $company->name,
                        'employee_id'   => $employee->id,
                        'employee_name' => $fullName,
                        'schema'        => $schema,
                    ];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    public static function resolveAuth(Request $request): ?array
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        $data = Cache::get("emp_token:{$token}");
        if (!$data) return null;

        // Set schema for subsequent queries
        DB::connection('tenant')->statement("SET search_path TO \"{$data['schema']}\", public");

        return $data;
    }
}
