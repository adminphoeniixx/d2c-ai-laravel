<?php
declare(strict_types=1);
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryPartner extends Model
{
    protected $connection = 'tenant';
    protected $fillable = ['name', 'slug', 'api_base_url', 'api_credentials', 'gstin', 'is_active', 'api_connected', 'settings'];
    protected function casts(): array { return ['is_active' => 'boolean', 'api_connected' => 'boolean', 'settings' => 'array']; }
    public function invoices(): HasMany { return $this->hasMany(LogisticsInvoice::class, 'delivery_partner_id'); }
    public function shipments(): HasMany { return $this->hasMany(LogisticsShipment::class, 'delivery_partner_id'); }

    public static function seedDefaults(): void
    {
        $partners = [
            ['name' => 'Delhivery', 'slug' => 'delhivery'],
            ['name' => 'Shiprocket', 'slug' => 'shiprocket'],
            ['name' => 'BlueDart', 'slug' => 'bluedart'],
            ['name' => 'Ecom Express', 'slug' => 'ecom-express'],
            ['name' => 'DTDC', 'slug' => 'dtdc'],
            ['name' => 'Shadowfax', 'slug' => 'shadowfax'],
            ['name' => 'Xpressbees', 'slug' => 'xpressbees'],
            ['name' => 'India Post', 'slug' => 'india-post'],
            ['name' => 'Other', 'slug' => 'other'],
        ];
        foreach ($partners as $p) {
            static::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
