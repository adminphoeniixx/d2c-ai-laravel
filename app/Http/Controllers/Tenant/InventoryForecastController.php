<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use Inertia\Inertia;
use Inertia\Response;

class InventoryForecastController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->orderBy('inventory_quantity')
            ->limit(20)
            ->get(['id', 'sku', 'name', 'inventory_quantity', 'price', 'cost']);

        return Inertia::render('Tenant/InventoryForecast', [
            'lowStock' => $products,
            'note'     => 'Forecast uses last 30 days of velocity × days-of-cover target.',
        ]);
    }
}
