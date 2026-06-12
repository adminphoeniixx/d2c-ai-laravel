<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        /** @var \App\Models\User|null $user */
        $user    = $request->user();
        $company = $user?->company;

        return array_merge(parent::share($request), [
            'app' => [
                'name'    => config('app.name'),
                'env'     => app()->environment(),
                'brand'   => 'heyd2c',
                'tagline' => 'D2C Ops AI',
            ],

            'auth' => [
                'user' => $user ? [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'initials'    => $user->initials(),
                    'is_admin'    => (bool) $user->is_admin,
                    'company_id'  => $user->company_id,
                    'roles'       => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null,
            ],

            'company' => $company ? [
                'id'     => $company->id,
                'slug'   => $company->slug,
                'name'   => $company->name,
                'status' => $company->status,
                'plan'   => $company->plan,
                'gstin'  => $company->gstin,
                'business_category' => $company->business_category,
                'letterhead_url' => $company->letterhead_url ?? null,
                'integrations' => [
                    'shopify' => (bool) $company->shopify_connected_at,
                    'woo'     => (bool) $company->woo_connected_at,
                ],
            ] : null,

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
            ],
        ]);
    }
}
