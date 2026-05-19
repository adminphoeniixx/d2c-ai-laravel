<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Expense;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Runs INSIDE each tenant schema. Creates plausible demo data
 * for the dashboards to render meaningfully without external syncs.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Products
        $products = collect([
            ['name' => 'Classic Tee - Black',   'sku' => 'TEE-BLK-M', 'price' => 899,  'cost' => 280, 'qty' => 240],
            ['name' => 'Classic Tee - White',   'sku' => 'TEE-WHT-M', 'price' => 899,  'cost' => 280, 'qty' => 180],
            ['name' => 'Hoodie Oversized',      'sku' => 'HOD-OV-L',  'price' => 1999, 'cost' => 620, 'qty' => 90],
            ['name' => 'Canvas Tote',           'sku' => 'TOTE-01',   'price' => 599,  'cost' => 150, 'qty' => 320],
            ['name' => 'Joggers Fleece',        'sku' => 'JOG-FL-M',  'price' => 1499, 'cost' => 470, 'qty' => 140],
        ])->map(function ($p) {
            return Product::create([
                'external_id'        => 'demo-'.Str::random(8),
                'provider'           => 'shopify',
                'sku'                => $p['sku'],
                'name'               => $p['name'],
                'vendor'             => 'House Brand',
                'product_type'       => 'Apparel',
                'status'             => 'active',
                'price'              => $p['price'],
                'compare_at_price'   => $p['price'] * 1.2,
                'cost'               => $p['cost'],
                'inventory_quantity' => $p['qty'],
                'tags'               => ['bestseller'],
            ]);
        });

        // Orders across the last 6 months (to drive the line chart)
        $startOfPeriod = Carbon::now()->subMonths(6)->startOfMonth();
        for ($i = 0; $i < 240; $i++) {
            $placedAt = Carbon::createFromTimestamp(
                random_int($startOfPeriod->getTimestamp(), Carbon::now()->getTimestamp())
            );
            $product = $products->random();
            $qty = random_int(1, 3);
            $subtotal = (float) $product->price * $qty;
            $discount = random_int(0, 15) === 0 ? round($subtotal * 0.1, 2) : 0;
            $shipping = random_int(0, 3) === 0 ? 0 : 79;
            $tax = round(($subtotal - $discount) * 0.05, 2);
            $total = $subtotal - $discount + $shipping + $tax;

            $order = Order::create([
                'external_id'        => 'demo-'.Str::random(10),
                'provider'           => 'shopify',
                'order_number'       => '#'.str_pad((string)(1000 + $i), 5, '0', STR_PAD_LEFT),
                'status'             => ['paid', 'paid', 'paid', 'fulfilled', 'pending', 'refunded'][random_int(0, 5)],
                'financial_status'   => 'paid',
                'fulfillment_status' => 'fulfilled',
                'currency'           => 'INR',
                'subtotal'           => $subtotal,
                'total_tax'          => $tax,
                'total_discount'     => $discount,
                'total_shipping'     => $shipping,
                'total_amount'       => $total,
                'customer_email'     => 'customer'.random_int(1, 80).'@example.com',
                'customer_name'      => fake()->name(),
                'customer_phone'     => '+91'.random_int(7000000000, 9999999999),
                'shipping_address'   => ['city' => fake()->city(), 'state' => 'MH', 'country' => 'IN'],
                'line_item_count'    => $qty,
                'tags'               => [],
                'raw_payload'        => [],
                'placed_at'          => $placedAt,
            ]);

            OrderItem::create([
                'order_id'     => $order->id,
                'external_id'  => 'demo-li-'.Str::random(6),
                'sku'          => $product->sku,
                'product_name' => $product->name,
                'quantity'     => $qty,
                'unit_price'   => $product->price,
                'total_price'  => $subtotal,
            ]);
        }

        // Expenses — ads, shipping, tools, payroll over last 6 months
        $categories = [
            'ads'       => ['label' => 'Meta Ads', 'monthly' => 65000],
            'ads:google'=> ['label' => 'Google Ads', 'monthly' => 42000],
            'shipping'  => ['label' => 'Shipping & Fulfillment', 'monthly' => 38000],
            'tools'     => ['label' => 'SaaS Tools', 'monthly' => 9500],
            'payroll'   => ['label' => 'Payroll', 'monthly' => 185000],
            'rent'      => ['label' => 'Office Rent', 'monthly' => 35000],
        ];

        for ($m = 0; $m < 7; $m++) {
            $month = Carbon::now()->subMonths($m)->startOfMonth();
            foreach ($categories as $key => $def) {
                Expense::create([
                    'category'    => explode(':', $key)[0],
                    'source'      => 'manual',
                    'label'       => $def['label'].' · '.$month->format('M Y'),
                    'amount'      => $def['monthly'] * (0.85 + mt_rand(0, 300) / 1000),
                    'currency'    => 'INR',
                    'occurred_at' => $month->copy()->addDays(random_int(1, 27)),
                ]);
            }
        }

        $this->command?->info('✓ Tenant seeded: '.Order::count().' orders, '.Expense::count().' expenses.');
    }
}
