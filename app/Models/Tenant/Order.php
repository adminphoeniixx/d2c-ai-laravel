<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-scoped Order model.
 * Rows live inside `tenant_<uuid>.orders`.
 *
 * @property int     $id
 * @property string  $external_id         Shopify/Woo order ID
 * @property string  $provider            shopify|woocommerce
 * @property string  $order_number
 * @property string  $status              pending|paid|fulfilled|cancelled|refunded
 * @property string  $financial_status
 * @property string  $fulfillment_status
 * @property string  $currency
 * @property float   $subtotal
 * @property float   $total_tax
 * @property float   $total_discount
 * @property float   $total_shipping
 * @property float   $total_amount
 * @property string  $customer_email
 * @property string  $customer_name
 * @property array   $shipping_address
 * @property array   $billing_address
 * @property array   $raw_payload
 * @property \Carbon\CarbonImmutable $placed_at
 */
class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'external_id', 'provider', 'order_number', 'status',
        'financial_status', 'fulfillment_status', 'currency',
        'subtotal', 'total_tax', 'total_discount', 'total_shipping', 'total_amount',
        // GST fields
        'taxable_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'gst_rate', 'place_of_supply', 'is_intra_state', 'buyer_state_code',
        // Customer
        'customer_email', 'customer_name', 'customer_phone',
        'shipping_address', 'billing_address',
        'line_item_count', 'tags', 'raw_payload', 'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'         => 'decimal:2',
            'total_tax'        => 'decimal:2',
            'total_discount'   => 'decimal:2',
            'total_shipping'   => 'decimal:2',
            'total_amount'     => 'decimal:2',
            'taxable_amount'   => 'decimal:2',
            'cgst_amount'      => 'decimal:2',
            'sgst_amount'      => 'decimal:2',
            'igst_amount'      => 'decimal:2',
            'gst_rate'         => 'decimal:2',
            'is_intra_state'   => 'boolean',
            'shipping_address' => 'array',
            'billing_address'  => 'array',
            'tags'             => 'array',
            'raw_payload'      => 'array',
            'placed_at'        => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
