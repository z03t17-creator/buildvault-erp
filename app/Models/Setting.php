<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const CACHE_PREFIX = 'app_setting:';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::remember(
            self::CACHE_PREFIX.$key,
            3600,
            function () use ($key, $default) {
                $row = static::query()->where('key', $key)->first();

                return $row?->value ?? $default;
            },
        );
    }

    public static function putValue(string $key, string|int|float $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value],
        );

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    public static function getFloat(string $key, float $default): float
    {
        $raw = static::getValue($key);

        return $raw === null || $raw === '' ? $default : (float) $raw;
    }

    public static function getInt(string $key, int $default): int
    {
        $raw = static::getValue($key);

        return $raw === null || $raw === '' ? $default : (int) $raw;
    }
}
