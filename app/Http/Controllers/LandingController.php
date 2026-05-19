<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Landing/Index', [
            'metrics' => [
                ['label' => 'Active brands',    'value' => '240+'],
                ['label' => 'Orders analysed',  'value' => '1.2M'],
                ['label' => 'Ad spend tracked', 'value' => '₹84Cr'],
                ['label' => 'Hours saved / wk', 'value' => '12k'],
            ],
            'integrations' => ['Shopify', 'WooCommerce', 'Meta Ads', 'Google Ads', 'Razorpay'],
        ]);
    }

    public function pricing(): Response
    {
        return Inertia::render('Landing/Pricing', [
            'plans' => [
                ['name' => 'Free',       'price' => 0,    'features' => ['1 store', '1 seat', 'Basic dashboard']],
                ['name' => 'Pro',        'price' => 2499, 'features' => ['3 stores', '5 seats', 'AI Copilot', 'Real-time alerts']],
                ['name' => 'Enterprise', 'price' => 9999, 'features' => ['Unlimited stores', 'SSO', 'Dedicated support']],
            ],
        ]);
    }

    public function features(): Response
    {
        return Inertia::render('Landing/Features');
    }
}
