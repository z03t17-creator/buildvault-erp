<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Support\AuditActions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ExchangeRateService
{
    public const FALLBACK_RATE = 1310.0;

    public const CACHE_KEY = 'exchange_rate:usd_iqd';

    public const CACHE_TTL_SECONDS = 12 * 60 * 60;

    public const SOURCE_API = 'exchangerate-api';

    public const SOURCE_FALLBACK = 'fallback';

    public const SOURCE_MANUAL = 'manual';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

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
     * Manually override the USD→IQD rate (persists + caches; audited).
     */
    public function override(float $rate, ?string $note = null): float
    {
        if ($rate <= 0) {
            throw new InvalidArgumentException('FX rate must be greater than zero.');
        }

        $rate = round($rate, 6);
        $previous = Cache::get(self::CACHE_KEY);

        $this->persist('USD', 'IQD', $rate, self::SOURCE_MANUAL);
        Cache::put(self::CACHE_KEY, $rate, self::CACHE_TTL_SECONDS);

        $this->audit->log(
            AuditActions::FX_RATE_OVERRIDDEN,
            sprintf('FX rate overridden to %.6f IQD/USD', $rate),
            null,
            [
                'base_currency' => 'USD',
                'target_currency' => 'IQD',
                'rate' => $rate,
                'previous_rate' => $previous !== null ? (float) $previous : null,
                'source' => self::SOURCE_MANUAL,
                'note' => $note,
            ],
        );

        return $rate;
    }

    /**
     * Explicit USD↔IQD conversion — the ONLY allowed blend path.
     * Always audit-logged. Dashboards/settlement must not call getUsdToIqd()
     * to invent the other currency.
     *
     * @return array{from: string, to: string, amount_from: float, amount_to: float, rate: float}
     */
    public function convertExplicit(
        float $amount,
        string $from,
        string $to,
        ?float $rate = null,
        ?string $note = null,
    ): array {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Conversion amount must be greater than zero.');
        }

        if (! in_array($from, ['USD', 'IQD'], true) || ! in_array($to, ['USD', 'IQD'], true)) {
            throw new InvalidArgumentException('Only USD and IQD are supported.');
        }

        if ($from === $to) {
            throw new InvalidArgumentException('from and to currencies must differ.');
        }

        $rate ??= $this->getUsdToIqd();
        if ($rate <= 0) {
            throw new InvalidArgumentException('FX rate must be greater than zero.');
        }

        $amount = round($amount, 2);
        $amountTo = $from === 'USD'
            ? round($amount * $rate, 2)
            : round($amount / $rate, 2);

        $this->audit->log(
            AuditActions::FX_EXPLICIT_CONVERSION,
            sprintf(
                'Explicit FX conversion: %.2f %s → %.2f %s @ %.6f',
                $amount,
                $from,
                $amountTo,
                $to,
                $rate,
            ),
            null,
            [
                'from' => $from,
                'to' => $to,
                'amount_from' => $amount,
                'amount_to' => $amountTo,
                'rate' => $rate,
                'note' => $note,
            ],
        );

        return [
            'from' => $from,
            'to' => $to,
            'amount_from' => $amount,
            'amount_to' => $amountTo,
            'rate' => round($rate, 6),
        ];
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
