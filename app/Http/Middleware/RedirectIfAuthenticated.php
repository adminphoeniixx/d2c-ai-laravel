<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): mixed
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Admin visiting company login → redirect to admin panel
            if ($user->is_admin) {
                return redirect()->route('admin.dashboard');
            }

            // Company user → redirect to their tenant dashboard
            if ($user->company) {
                return redirect()->route('tenant.dashboard', ['tenant' => $user->company->slug]);
            }

            return redirect('/');
        }

        return $next($request);
    }
}
