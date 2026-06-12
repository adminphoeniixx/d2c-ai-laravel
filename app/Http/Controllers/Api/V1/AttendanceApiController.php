<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Employee;
use App\Models\Tenant\LeaveBalance;
use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\LeaveType;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceApiController extends Controller
{
    /**
     * POST /api/v1/attendance/check-in
     * Body: { location: {lat, lng, accuracy}, face_verified: bool }
     */
    public function checkIn(Request $request): JsonResponse
    {
        $auth = AuthController::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        DB::connection('tenant')->statement("SET search_path TO \"{$auth['schema']}\", public");

        $employee = Employee::findOrFail($auth['employee_id']);
        $service = new AttendanceService();

        // Validate geo-fence
        $location = $request->input('location');
        if (!$service->validateLocation($location)) {
            DB::connection('tenant')->statement("SET search_path TO public");
            return response()->json(['error' => 'You are not within the allowed location radius', 'code' => 'GEO_FENCE'], 422);
        }

        $result = $service->checkIn($employee, [
            'location'      => $location,
            'face_verified' => $request->boolean('face_verified', false),
            'source'        => 'app',
            'ip_address'    => $request->ip(),
        ]);

        DB::connection('tenant')->statement("SET search_path TO public");

        if (!$result['success']) {
            return response()->json(['error' => $result['error'], 'attendance' => $result['attendance'] ?? null], 422);
        }

        return response()->json([
            'success'      => true,
            'message'      => $result['late'] ? "Checked in — {$result['late_minutes']} minutes late" : 'Checked in successfully',
            'attendance'   => $this->formatAttendance($result['attendance']),
            'shift'        => $result['shift'],
            'late'         => $result['late'],
            'late_minutes' => $result['late_minutes'],
            'penalty'      => $result['penalty'],
        ]);
    }

    /**
     * POST /api/v1/attendance/check-out
     * Body: { location: {lat, lng}, face_verified: bool }
     */
    public function checkOut(Request $request): JsonResponse
    {
        $auth = AuthController::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        DB::connection('tenant')->statement("SET search_path TO \"{$auth['schema']}\", public");

        $employee = Employee::findOrFail($auth['employee_id']);
        $service = new AttendanceService();

        $result = $service->checkOut($employee, [
            'location'      => $request->input('location'),
            'face_verified' => $request->boolean('face_verified', false),
        ]);

        DB::connection('tenant')->statement("SET search_path TO public");

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Checked out successfully',
            'attendance'   => $this->formatAttendance($result['attendance']),
            'worked_hours' => $result['worked_hours'],
            'overtime'     => $result['overtime'],
        ]);
    }

    /**
     * GET /api/v1/attendance/my-status
     */
    public function myStatus(Request $request): JsonResponse
    {
        $auth = AuthController::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        DB::connection('tenant')->statement("SET search_path TO \"{$auth['schema']}\", public");

        $today = Attendance::where('employee_id', $auth['employee_id'])
            ->whereDate('date', Carbon::today())
            ->first();

        $monthSummary = Attendance::where('employee_id', $auth['employee_id'])
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->selectRaw("
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days,
                SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leaves,
                SUM(CASE WHEN is_late = true THEN 1 ELSE 0 END) as late_count,
                SUM(worked_hours) as total_worked,
                SUM(overtime_hours) as total_overtime,
                SUM(penalty_amount) as total_penalty
            ")->first();

        DB::connection('tenant')->statement("SET search_path TO public");

        return response()->json([
            'today'   => $today ? $this->formatAttendance($today) : null,
            'month'   => $monthSummary,
        ]);
    }

    /**
     * GET /api/v1/attendance/my-history?month=2026-05
     */
    public function myHistory(Request $request): JsonResponse
    {
        $auth = AuthController::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $month = $request->input('month', now()->format('Y-m'));

        DB::connection('tenant')->statement("SET search_path TO \"{$auth['schema']}\", public");

        $records = Attendance::where('employee_id', $auth['employee_id'])
            ->whereYear('date', substr($month, 0, 4))
            ->whereMonth('date', substr($month, 5, 2))
            ->orderBy('date')
            ->get()
            ->map(fn ($a) => $this->formatAttendance($a));

        DB::connection('tenant')->statement("SET search_path TO public");

        return response()->json(['month' => $month, 'records' => $records]);
    }

    /**
     * POST /api/v1/leave/apply
     * Body: { leave_type_id, from_date, to_date, days, reason }
     */
    public function applyLeave(Request $request): JsonResponse
    {
        $auth = AuthController::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'from_date'     => ['required', 'date', 'after_or_equal:today'],
            'to_date'       => ['required', 'date', 'after_or_equal:from_date'],
            'days'          => ['required', 'numeric', 'min:0.5'],
            'reason'        => ['nullable', 'string', 'max:500'],
        ]);

        DB::connection('tenant')->statement("SET search_path TO \"{$auth['schema']}\", public");

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

        // Check balance
        $balance = LeaveBalance::where('employee_id', $auth['employee_id'])
            ->where('leave_type_id', $leaveType->id)
            ->where('year', Carbon::parse($validated['from_date'])->year)
            ->first();

        if ($balance && $leaveType->annual_quota > 0) {
            $remaining = $balance->remaining;
            if ($validated['days'] > $remaining) {
                DB::connection('tenant')->statement("SET search_path TO public");
                return response()->json(['error' => "Insufficient {$leaveType->code} balance. Available: {$remaining} days"], 422);
            }
        }

        // Check for overlapping requests
        $overlap = LeaveRequest::where('employee_id', $auth['employee_id'])
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('from_date', [$validated['from_date'], $validated['to_date']])
                  ->orWhereBetween('to_date', [$validated['from_date'], $validated['to_date']]);
            })->exists();

        if ($overlap) {
            DB::connection('tenant')->statement("SET search_path TO public");
            return response()->json(['error' => 'Overlapping leave request exists'], 422);
        }

        $leave = LeaveRequest::create([
            'employee_id'   => $auth['employee_id'],
            'leave_type_id' => $leaveType->id,
            'from_date'     => $validated['from_date'],
            'to_date'       => $validated['to_date'],
            'days'          => $validated['days'],
            'reason'        => $validated['reason'] ?? null,
            'status'        => $leaveType->requires_approval ? 'pending' : 'approved',
        ]);

        // Auto-approve: update balance immediately
        if (!$leaveType->requires_approval) {
            if ($balance) {
                $balance->increment('used', $validated['days']);
            }
        }

        DB::connection('tenant')->statement("SET search_path TO public");

        return response()->json([
            'success' => true,
            'message' => $leaveType->requires_approval ? 'Leave request submitted for approval' : 'Leave approved automatically',
            'leave'   => $leave,
        ]);
    }

    /**
     * GET /api/v1/leave/balance
     */
    public function leaveBalance(Request $request): JsonResponse
    {
        $auth = AuthController::resolveAuth($request);
        if (!$auth) return response()->json(['error' => 'Unauthorized'], 401);

        DB::connection('tenant')->statement("SET search_path TO \"{$auth['schema']}\", public");

        // Initialize balances if not yet
        LeaveBalance::initializeForEmployee($auth['employee_id'], now()->year);

        $balances = LeaveBalance::where('employee_id', $auth['employee_id'])
            ->where('year', now()->year)
            ->with('leaveType:id,name,code,is_paid')
            ->get()
            ->map(fn ($b) => [
                'type'            => $b->leaveType->name ?? '',
                'code'            => $b->leaveType->code ?? '',
                'is_paid'         => $b->leaveType->is_paid ?? true,
                'allocated'       => (float) $b->allocated,
                'used'            => (float) $b->used,
                'carried_forward' => (float) $b->carried_forward,
                'remaining'       => $b->remaining,
            ]);

        DB::connection('tenant')->statement("SET search_path TO public");

        return response()->json(['year' => now()->year, 'balances' => $balances]);
    }

    protected function formatAttendance(Attendance $a): array
    {
        return [
            'id'             => $a->id,
            'date'           => $a->date->format('Y-m-d'),
            'check_in'       => $a->check_in,
            'check_out'      => $a->check_out,
            'worked_hours'   => (float) $a->worked_hours,
            'overtime_hours' => (float) $a->overtime_hours,
            'late_minutes'   => $a->late_minutes,
            'is_late'        => $a->is_late,
            'penalty_amount' => (float) $a->penalty_amount,
            'status'         => $a->status,
            'face_verified'  => $a->face_verified,
            'source'         => $a->source,
        ];
    }
}
