<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->service = new ExchangeRateService;
    }

    public function test_fetches_usd_to_iqd_rate_persists_and_caches(): void
    {
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'base' => 'USD',
                'rates' => [
                    'IQD' => 1325.5,
                ],
            ], 200),
        ]);

        $rate = $this->service->getUsdToIqd();

        $this->assertSame(1325.5, $rate);

        $this->assertDatabaseCount('exchange_rates', 1);
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'USD',
            'target_currency' => 'IQD',
            'source' => ExchangeRateService::SOURCE_API,
        ]);

        $stored = ExchangeRate::query()->first();
        $this->assertSame('1325.500000', (string) $stored->rate);

        // Second call should use cache — no additional HTTP or DB rows.
        $cached = $this->service->getUsdToIqd();
        $this->assertSame(1325.5, $cached);
        $this->assertDatabaseCount('exchange_rates', 1);
        Http::assertSentCount(1);
    }

    public function test_falls_back_to_1310_when_api_fails(): void
    {
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response('unavailable', 503),
        ]);

        $rate = $this->service->getUsdToIqd();

        $this->assertSame(ExchangeRateService::FALLBACK_RATE, $rate);
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'USD',
            'target_currency' => 'IQD',
            'source' => ExchangeRateService::SOURCE_FALLBACK,
            'rate' => ExchangeRateService::FALLBACK_RATE,
        ]);
    }

    public function test_falls_back_when_response_missing_iqd_rate(): void
    {
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'base' => 'USD',
                'rates' => [
                    'EUR' => 0.92,
                ],
            ], 200),
        ]);

        $rate = $this->service->getUsdToIqd();

        $this->assertSame(1310.0, $rate);
        $this->assertDatabaseHas('exchange_rates', [
            'source' => ExchangeRateService::SOURCE_FALLBACK,
        ]);
    }

    public function test_falls_back_on_transport_exception(): void
    {
        Http::fake([
            'api.exchangerate-api.com/*' => fn () => throw new \RuntimeException('network down'),
        ]);

        $rate = $this->service->getUsdToIqd();

        $this->assertSame(1310.0, $rate);
        $this->assertSame(1, ExchangeRate::query()->where('source', 'fallback')->count());
    }
}
