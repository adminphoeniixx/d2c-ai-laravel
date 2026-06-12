<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportCategory extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['name', 'slug', 'auto_reply', 'sla_hours', 'is_active', 'sort_order'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function tickets(): HasMany { return $this->hasMany(SupportTicket::class, 'category_id'); }
    public function faqs(): HasMany { return $this->hasMany(SupportFaq::class, 'category_id'); }

    public static function seedDefaults(): void
    {
        $defaults = [
            ['name' => 'Order Issue', 'slug' => 'order-issue', 'auto_reply' => 'Thank you for reaching out about your order. We are looking into this and will respond within 24 hours.', 'sla_hours' => 24],
            ['name' => 'Return & Refund', 'slug' => 'return-refund', 'auto_reply' => 'We received your return/refund request. Our team will review and respond within 48 hours.', 'sla_hours' => 48],
            ['name' => 'Product Query', 'slug' => 'product-query', 'auto_reply' => 'Thank you for your interest! We will get back to you shortly.', 'sla_hours' => 24],
            ['name' => 'Shipping', 'slug' => 'shipping', 'auto_reply' => 'We are checking on your shipment status and will update you soon.', 'sla_hours' => 12],
            ['name' => 'Payment Issue', 'slug' => 'payment-issue', 'auto_reply' => 'We are investigating your payment concern. Please allow us 24-48 hours.', 'sla_hours' => 24],
            ['name' => 'Other', 'slug' => 'other', 'auto_reply' => 'Thank you for contacting us. We will respond as soon as possible.', 'sla_hours' => 48],
        ];
        foreach ($defaults as $i => $d) {
            static::firstOrCreate(['slug' => $d['slug']], array_merge($d, ['sort_order' => $i]));
        }
    }
}
