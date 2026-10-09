<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Services\SimpleVaultService;
use App\Support\DualCurrency;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VaultDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_dashboard_shows_simple_vault_snapshot(): void
    {
        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1325.5],
            ], 200),
        ]);

        $user = $this->userWithRole();
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $service = app(SimpleVaultService::class);
        $service->postAdvance([
            'amount' => 1000,
            'currency' => DualCurrency::USD,
            'occurred_on' => now()->toDateString(),
            'vault_id' => $vault->id,
        ]);

        $timeStaff = Staff::query()->create([
            'name' => 'Time Laminate',
            'kind' => Staff::KIND_TIME,
            'trade' => 'laminate',
        ]);
        $service->postJobPay([
            'staff_id' => $timeStaff->id,
            'amount' => 200,
            'currency' => DualCurrency::USD,
            'occurred_on' => now()->toDateString(),
            'vault_id' => $vault->id,
        ]);

        Staff::query()->create([
            'name' => 'Salary Person',
            'kind' => Staff::KIND_SALARY,
            'monthly_salary' => 500,
            'currency' => DualCurrency::USD,
        ]);

        $response = $this->actingAs($user)->get(route('dashboards.vault'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboards/Vault')
            ->where('vault.name', VaultSeeder::NAME)
            ->where('available_cash.USD', 720)
            ->where('available_cash.IQD', 0)
            ->where('fx.auto_blend', false)
            ->has('insurance_unlocks', 1)
            ->where('insurance_unlocks.0.amount', 100)
            ->has('staff_holds', 1)
            ->where('staff_holds.0.staff_name', 'Time Laminate')
            ->where('staff_holds.0.amount', 20)
            ->where('estimates.USD.salaries_to_pay', 500)
            ->where('estimates.USD.covers', true)
            ->where('liquidity.available_usd', 720)
            ->where('liquidity.available_iqd', 0));
    }

    public function test_refresh_fx_endpoint(): void
    {
        Cache::flush();
        Http::fake([
            'api.exchangerate-api.com/*' => Http::response([
                'rates' => ['IQD' => 1400],
            ], 200),
        ]);

        $user = $this->userWithRole();
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);

        $response = $this->actingAs($user)
            ->from(route('dashboards.vault'))
            ->post(route('dashboards.vault.refresh-fx'));

        $response->assertRedirect(route('dashboards.vault'));
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'USD',
            'target_currency' => 'IQD',
            'rate' => 1400,
        ]);
    }
}
