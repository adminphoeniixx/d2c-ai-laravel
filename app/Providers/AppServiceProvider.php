<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Integrations\Shopify\ShopifyOAuth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShopifyOAuth::class, fn () => ShopifyOAuth::make());
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
