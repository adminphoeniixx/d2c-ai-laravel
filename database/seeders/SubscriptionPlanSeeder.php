<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'               => 'Free',
                'slug'               => 'free',
                'description'        => 'Get started with heyd2c. Up to 3,000 lifetime orders.',
                'price'              => 0,
                'price_yearly'       => 0,
                'order_limit'        => 3000,
                'store_limit'        => 1,
                'team_member_limit'  => 1,
                'data_history_days'  => 30,
                'per_order_charge'   => 0,
                'is_free'            => true,
                'is_active'          => true,
                'is_featured'        => false,
                'features'           => json_encode(['expenses', 'orders', 'banking', 'logistics', 'gst']),
                'sort_order'         => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'name'               => 'Basic',
                'slug'               => 'basic',
                'description'        => 'For growing D2C brands with unlimited orders.',
                'price'              => 999,
                'price_yearly'       => 9990,
                'order_limit'        => -1,
                'store_limit'        => 1,
                'team_member_limit'  => 2,
                'data_history_days'  => 180,
                'per_order_charge'   => 1.50,
                'is_free'            => false,
                'is_active'          => true,
                'is_featured'        => false,
                'features'           => json_encode(['expenses', 'orders', 'banking', 'logistics', 'gst', 'hr', 'payroll']),
                'sort_order'         => 2,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'name'               => 'Growth',
                'slug'               => 'growth',
                'description'        => 'Scale your operations with multi-store support.',
                'price'              => 2999,
                'price_yearly'       => 29990,
                'order_limit'        => -1,
                'store_limit'        => 3,
                'team_member_limit'  => 5,
                'data_history_days'  => 365,
                'per_order_charge'   => 1.00,
                'is_free'            => false,
                'is_active'          => true,
                'is_featured'        => true,
                'features'           => json_encode(['expenses', 'orders', 'banking', 'logistics', 'gst', 'hr', 'payroll', 'ai', 'marketplace']),
                'sort_order'         => 3,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'name'               => 'Scale',
                'slug'               => 'scale',
                'description'        => 'Unlimited everything for enterprise D2C brands.',
                'price'              => 7999,
                'price_yearly'       => 79990,
                'order_limit'        => -1,
                'store_limit'        => -1,
                'team_member_limit'  => -1,
                'data_history_days'  => -1,
                'per_order_charge'   => 0.50,
                'is_free'            => false,
                'is_active'          => true,
                'is_featured'        => false,
                'features'           => json_encode(['expenses', 'orders', 'banking', 'logistics', 'gst', 'hr', 'payroll', 'ai', 'marketplace', 'dedicated_support', 'custom_integrations']),
                'sort_order'         => 4,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
