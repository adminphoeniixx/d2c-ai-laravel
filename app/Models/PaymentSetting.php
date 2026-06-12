<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PaymentSetting extends Model
{
    protected $connection = 'pgsql';
    protected $table   = 'payment_settings';
    protected $guarded = ['id'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "payment_setting:{$key}";
        $cached   = \Illuminate\Support\Facades\Cache::store('file')->get($cacheKey);
        if ($cached !== null) return $cached;

        $value = static::where('key', $key)->value('value') ?? $default;
        \Illuminate\Support\Facades\Cache::store('file')->put($cacheKey, $value, 300);
        return $value;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        \Illuminate\Support\Facades\Cache::store('file')->forget("payment_setting:{$key}");
    }
}
