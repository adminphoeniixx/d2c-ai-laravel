<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ShopifyOrder extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'shopify_orders';

    protected $fillable = [

        'tenant_id',

        'shopify_id',
        'order_number',

        'currency',

        'financial_status',
        'fulfillment_status',

        'total_price',
        'subtotal_price',
        'total_discounts',

        'created_at_shopify',

        'customer',
        'products',

        'raw'

    ];
}