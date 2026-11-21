<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class Setting extends Model
{
    public const DEFAULTS = [
        'company_name' => 'Village City',
        'company_tagline' => 'From the farm to the city, within 24 hours.',
        'company_phone' => '',
        'company_email' => '',
        'company_address' => '',
        'default_delivery_fee' => '0',
        'forecast_weeks' => '4',
        'safety_stock_days' => '3',
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (static::$cache === null) {
            try {
                static::$cache = static::query()->pluck('value', 'key')->all();
            } catch (Throwable) {
                static::$cache = [];
            }
        }

        return static::$cache[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache = null;
    }

    /** Remove an override so the default applies again. */
    public static function forget(string $key): void
    {
        static::query()->whereKey($key)->delete();
        static::$cache = null;
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }
}
