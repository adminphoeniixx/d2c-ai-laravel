<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
 use App\Models\Tenant;

class SetTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
   

    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        abort_unless($user && $user->tenant_id, 403);

        $tenant = Tenant::find($user->tenant_id);

        abort_unless($tenant, 403);

        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
