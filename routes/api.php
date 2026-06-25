<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AttendanceApiController;
use App\Http\Controllers\Api\V1\KioskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/...
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Auth (public) ────────────────────────
    Route::post('/auth/send-otp',      [AuthController::class, 'sendOtp']);
    Route::post('/auth/verify-otp',    [AuthController::class, 'verifyOtp']);

    // ── Registration OTP (public) ────────────
    Route::post('/register/send-otp',  [AuthController::class, 'registerSendOtp']);
    Route::post('/register/verify-otp',[AuthController::class, 'registerVerifyOtp']);

    // ── Authenticated (Bearer token) ─────────
    Route::post('/auth/register-face', [AuthController::class, 'registerFace']);
    Route::get('/me',                  [AuthController::class, 'me']);
    Route::delete('/account',          [AuthController::class, 'deleteAccount']);

    // ── Attendance ───────────────────────────
    Route::post('/attendance/check-in',  [AttendanceApiController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceApiController::class, 'checkOut']);
    Route::get('/attendance/my-status',  [AttendanceApiController::class, 'myStatus']);
    Route::get('/attendance/my-history', [AttendanceApiController::class, 'myHistory']);

    // ── Leave ────────────────────────────────
    Route::post('/leave/apply',   [AttendanceApiController::class, 'applyLeave']);
    Route::get('/leave/balance',  [AttendanceApiController::class, 'leaveBalance']);

    // ══════════════════════════════════════════
    // KIOSK / PUNCH MACHINE APIs
    // ══════════════════════════════════════════
    Route::prefix('kiosk')->group(function () {
        // Auth (public)
        Route::post('/send-otp',   [KioskController::class, 'sendOtp']);
        Route::post('/verify-otp', [KioskController::class, 'verifyOtp']);

        // Authenticated (kiosk_token)
        Route::get('/employees',                    [KioskController::class, 'employees']);
        Route::post('/employees/{id}/face',         [KioskController::class, 'saveFace']);
        Route::delete('/employees/{id}/face',       [KioskController::class, 'removeFace']);
        Route::post('/punch-in',                    [KioskController::class, 'punchIn']);
        Route::post('/punch-out',                   [KioskController::class, 'punchOut']);
        Route::get('/today',                        [KioskController::class, 'today']);
    });
});
