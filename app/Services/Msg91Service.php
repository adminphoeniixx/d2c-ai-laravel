<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Msg91Service
{
    protected string $authKey;
    protected string $templateId;

    public function __construct()
    {
        $this->authKey    = config('services.msg91.auth_key', '');
        $this->templateId = config('services.msg91.otp_template_id', '');
    }

    /**
     * Send OTP via MSG91 SendOTP API.
     *
     * MSG91 generates and sends the OTP itself.
     * We pass our own OTP so we can verify it server-side.
     *
     * API: POST https://control.msg91.com/api/v5/otp
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        if (empty($this->authKey) || empty($this->templateId)) {
            Log::warning('MSG91: auth_key or otp_template_id not configured');
            return false;
        }

        // Ensure phone has country code (91 for India)
        $mobile = $this->formatPhone($phone);

        try {
            $response = Http::withHeaders([
                'authkey'      => $this->authKey,
                'Content-Type' => 'application/json',
            ])->post('https://control.msg91.com/api/v5/otp', [
                'template_id' => $this->templateId,
                'mobile'      => $mobile,
                'otp'         => $otp,
            ]);

            $body = $response->json();

            if ($response->successful() && ($body['type'] ?? '') === 'success') {
                Log::info("MSG91 OTP sent to {$mobile}");
                return true;
            }

            Log::error('MSG91 OTP failed', [
                'phone'    => $mobile,
                'status'   => $response->status(),
                'response' => $body,
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('MSG91 OTP exception', [
                'phone' => $mobile,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verify OTP via MSG91 (optional — we verify server-side via cache,
     * but this can be used as a secondary check).
     *
     * API: POST https://control.msg91.com/api/v5/otp/verify
     */
    public function verifyOtp(string $phone, string $otp): bool
    {
        $mobile = $this->formatPhone($phone);

        try {
            $response = Http::withHeaders([
                'authkey' => $this->authKey,
            ])->get('https://control.msg91.com/api/v5/otp/verify', [
                'mobile' => $mobile,
                'otp'    => $otp,
            ]);

            $body = $response->json();
            return ($body['type'] ?? '') === 'success';

        } catch (\Throwable $e) {
            Log::error('MSG91 verify exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Resend OTP via MSG91.
     *
     * API: POST https://control.msg91.com/api/v5/otp/retry
     */
    public function resendOtp(string $phone, string $retryType = 'text'): bool
    {
        $mobile = $this->formatPhone($phone);

        try {
            $response = Http::withHeaders([
                'authkey' => $this->authKey,
            ])->post('https://control.msg91.com/api/v5/otp/retry', [
                'mobile'    => $mobile,
                'retrytype' => $retryType, // 'text' or 'voice'
            ]);

            $body = $response->json();
            return ($body['type'] ?? '') === 'success';

        } catch (\Throwable $e) {
            Log::error('MSG91 resend exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function formatPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // If 10 digits, prepend 91 (India)
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        // If already has country code
        return $digits;
    }
}
