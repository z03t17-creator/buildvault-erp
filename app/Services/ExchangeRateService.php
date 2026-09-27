<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExchangeRateService
{
    public const FALLBACK_RATE = 1310.0;

    public const CACHE_KEY = 'exchange_rate:usd_iqd';

    public const CACHE_TTL_SECONDS = 12 * 60 * 60;

    public const SOURCE_API = 'exchangerate-api';

    public const SOURCE_FALLBACK = 'fallback';

    /**
     * Return the USD→IQD rate, cached for 12 hours.
     */
    public function getUsdToIqd(): float
    {
        return (float) Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): float => $this->fetchAndPersist(),
        );
    }

    /**
     * Bypass cache and fetch a fresh rate (still persists history).
     */
    public function refresh(): float
    {
        Cache::forget(self::CACHE_KEY);

        $rate = $this->fetchAndPersist();

        Cache::put(self::CACHE_KEY, $rate, self::CACHE_TTL_SECONDS);

        return $rate;
    }

    /**
     * Fetch from the exchange-rate API, persist the row, fall back to 1310.00 on failure.
     */
    protected function fetchAndPersist(): float
    {
        $url = (string) config(
            'services.exchange_rate.url',
            'https://api.exchangerate-api.com/v4/latest/USD',
        );

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get($url);

            if ($response->successful()) {
                $rate = $this->extractIqdRate($response->json());

                if ($rate !== null && $rate > 0) {
                    $this->persist('USD', 'IQD', $rate, self::SOURCE_API);

                    return $rate;
                }
            }

            Log::warning('ExchangeRateService: unsuccessful or invalid API response', [
                'status' => $response->status(),
            ]);
        } catch (Throwable $e) {
            Log::warning('ExchangeRateService: fetch failed, using fallback', [
                'message' => $e->getMessage(),
            ]);
        }

        $this->persist('USD', 'IQD', self::FALLBACK_RATE, self::SOURCE_FALLBACK);

        return self::FALLBACK_RATE;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function extractIqdRate(?array $payload): ?float
    {
        if ($payload === null) {
            return null;
        }

        $rate = data_get($payload, 'rates.IQD')
            ?? data_get($payload, 'conversion_rates.IQD');

        if ($rate === null || ! is_numeric($rate)) {
            return null;
        }

        return round((float) $rate, 6);
    }

    protected function persist(string $base, string $target, float $rate, string $source): void
    {
        ExchangeRate::query()->create([
            'base_currency' => $base,
            'target_currency' => $target,
            'rate' => $rate,
            'source' => $source,
            'fetched_at' => now(),
        ]);
    }
}
