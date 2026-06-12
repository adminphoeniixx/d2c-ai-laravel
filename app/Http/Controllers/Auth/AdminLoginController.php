<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminLoginController extends Controller
{
    public function show()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return Inertia::render('Auth/AdminLogin', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password, 'is_admin' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);
            return back()->withErrors([
                'email' => 'These credentials do not match our admin records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // If 2FA is enabled, Fortify will handle the challenge
        $user = Auth::user();
        if ($user->hasEnabledTwoFactorAuthentication()) {
            Auth::logout();
            $request->session()->put('login.id', $user->getKey());
            $request->session()->put('login.remember', $request->boolean('remember'));
            return redirect()->route('two-factor.login');
        }

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
