<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Setting;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\InsuranceSettings;
use App\Services\PayoutService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InsuranceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(VaultSeeder::class);

        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);
    }

    public function test_defaults_are_ten_percent_and_six_months(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->ensureDefaults();

        $this->assertSame(10.0, $settings->holdbackPercent());
        $this->assertSame(6, $settings->maturityMonths());
        $this->assertSame(
            Carbon::parse('2026-01-15')->addMonthsNoOverflow(6)->toDateString(),
            RetentionHold::maturityFrom('2026-01-15')->toDateString(),
        );
    }

    public function test_admin_can_update_insurance_settings_via_ui_route(): void
    {
        $user = $this->userWithRole();
        app(InsuranceSettings::class)->ensureDefaults();

        $this->actingAs($user)
            ->put(route('retention-holds.settings'), [
                'holdback_pct' => 12.5,
                'maturity_months' => 4,
            ])
            ->assertRedirect();

        $settings = app(InsuranceSettings::class);
        $this->assertSame(12.5, $settings->holdbackPercent());
        $this->assertSame(4, $settings->maturityMonths());
        $this->assertSame('12.5', Setting::getValue(InsuranceSettings::HOLDBACK_PCT_KEY));
    }

    public function test_default_holdback_and_maturity_use_settings(): void
    {
        $settings = app(InsuranceSettings::class);
        $settings->update(20, 3);

        $project = Project::query()->create(['name' => 'Insure Site']);
        $vault = Vault::query()->first();
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Hold Worker',
            'daily_rate_usd' => 40,
        ]);

        ProjectAllocation::query()->create([
            'project_id' => $project->id,
            'expenses_pool_usd' => 0,
            'payroll_pool_usd' => 5000,
            'retention_pool_usd' => 0,
            'penalty_pool_usd' => 0,
            'profit_pool_usd' => 0,
        ]);
        $vault->update(['balance_usd' => 10000, 'balance_iqd' => 13100000]);

        $payout = app(PayoutService::class)->create([
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 1000,
            'vault_id' => $vault->id,
        ]);

        $this->assertSame('200.00', (string) $payout->retention_holdback);

        $approved = app(PayoutService::class)->approve($payout);
        $hold = RetentionHold::query()->where('payout_id', $approved->id)->first();
        $this->assertNotNull($hold);
        $this->assertSame(
            RetentionHold::maturityFrom($hold->hold_start)->toDateString(),
            $hold->maturity_date->toDateString(),
        );
        $this->assertSame(
            $hold->hold_start->copy()->addMonthsNoOverflow(3)->toDateString(),
            $hold->maturity_date->toDateString(),
        );
    }

    public function test_retention_holds_index_receives_settings(): void
    {
        $user = $this->userWithRole();
        app(InsuranceSettings::class)->update(15, 9);

        $this->actingAs($user)
            ->get(route('retention-holds.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RetentionHolds/Index')
                ->where('settings.holdback_pct', 15)
                ->where('settings.maturity_months', 9));
    }
}
