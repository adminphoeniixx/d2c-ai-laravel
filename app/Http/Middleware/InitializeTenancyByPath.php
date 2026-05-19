<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        if (! $slug) {
            abort(404, 'Tenant not specified.');
        }

        /** @var Company|null $company */
        $company = Company::query()->where('slug', $slug)->first();

        if (! $company) {
            abort(404, 'Workspace not found.');
        }

        if ($company->isSuspended()) {
            abort(403, 'This workspace has been suspended. Please contact support.');
        }

        $user = $request->user();
        if ($user && ! $user->is_admin && (int) $user->company_id !== (int) $company->id) {
            abort(403, 'You do not belong to this workspace.');
        }

        try {
            tenancy()->initialize($company);
        } catch (\Throwable $e) {
            abort(500, 'Tenant could not be initialised: ' . $e->getMessage());
        }

        app()->instance('current_company', $company);

        return $next($request);
    }
}
