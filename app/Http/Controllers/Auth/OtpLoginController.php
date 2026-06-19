<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Msg91Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class OtpLoginController extends Controller
{
    public function __construct(private Msg91Service $msg91) {}

    // Show OTP login page
    public function show()
    {
        // Admins must use /admin/login — redirect if already logged in
        if (auth()->check() && auth()->user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return Inertia::render('Auth/OtpLogin');
    }

    // Send OTP to phone
    public function send(Request $request)
    {
        $request->validate(['phone' => 'required|string|min:10']);

        $phone = $this->normalizePhone($request->phone);
        $last10 = substr($phone, -10);

        // Test account — skip SMS, always accept OTP 1234
        if ($last10 === '9876543210') {
            return back()->with('otp_sent', true);
        }

        $user = User::where(function($q) use ($phone, $last10) {
            $q->where('phone', $phone)
              ->orWhere('phone', '+' . $phone)
              ->orWhere('phone', $last10)
              ->orWhere('phone', '0' . $last10)
              ->orWhere('phone', '+91' . $last10)
              ->orWhere('phone', '91' . $last10);
        })->first();

        if (!$user) {
            return back()->withErrors(['phone' => 'No account found with this mobile number.']);
        }

        $otp = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        Cache::put("login_otp:{$phone}", $otp, now()->addMinutes(10));
        Cache::put("login_otp_attempts:{$phone}", 0, now()->addMinutes(10));

        $this->msg91->sendOtp($phone, $otp);

        return back()->with('otp_sent', true);
    }

    // Verify OTP and log in
    public function verify(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp'   => 'required|string|size:4',
        ]);

        $phone    = $this->normalizePhone($request->phone);
        $cacheKey = "login_otp:{$phone}";
        $attemptsKey = "login_otp_attempts:{$phone}";

        // Hardcoded test account for Shopify review — phone: 9876543210, OTP: 1234
        $last10 = substr($phone, -10);
        if ($last10 === '9876543210' && $request->otp === '1234') {
            $user = User::where(function($q) use ($phone, $last10) {
                $q->where('phone', $phone)
                  ->orWhere('phone', '+' . $phone)
                  ->orWhere('phone', $last10)
                  ->orWhere('phone', '0' . $last10)
                  ->orWhere('phone', '+91' . $last10)
                  ->orWhere('phone', '91' . $last10);
            })->first();

            if ($user) {
                Auth::login($user, $request->boolean('remember'));
                $request->session()->regenerate();
                $company = $user->company;
                if ($company) {
                    return redirect()->route('tenant.ai-insights', ['tenant' => $company->slug]);
                }
                return redirect()->intended('/');
            }
        }

        // Rate limit: max 5 attempts
        $attempts = Cache::get($attemptsKey, 0);
        if ($attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many attempts. Request a new OTP.']);
        }

        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp || $storedOtp !== $request->otp) {
            Cache::increment($attemptsKey);
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        // OTP valid — clear cache
        Cache::forget($cacheKey);
        Cache::forget($attemptsKey);

        // Find and log in user
        $last10 = substr($phone, -10);
        $user = User::where(function($q) use ($phone, $last10) {
            $q->where('phone', $phone)
              ->orWhere('phone', '+' . $phone)
              ->orWhere('phone', $last10)
              ->orWhere('phone', '0' . $last10)
              ->orWhere('phone', '+91' . $last10)
              ->orWhere('phone', '91' . $last10);
        })->first();

        if (!$user) {
            return back()->withErrors(['phone' => 'User not found.']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        // Redirect to their company dashboard
        $company = $user->company;
        if ($company) {
            return redirect()->route('tenant.ai-insights', ['tenant' => $company->slug]);
        }

        return redirect()->intended(route('dashboard'));
    }

    // Resend OTP
    public function resend(Request $request)
    {
        $request->validate(['phone' => 'required|string']);
        $phone = $this->normalizePhone($request->phone);

        $otp = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        Cache::put("login_otp:{$phone}", $otp, now()->addMinutes(10));
        Cache::put("login_otp_attempts:{$phone}", 0, now()->addMinutes(10));

        $this->msg91->sendOtp($phone, $otp);

        return back()->with(['otp_sent' => true, 'phone' => $phone]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 10) return '91' . $digits;
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) return $digits;
        return $digits;
    }
}
