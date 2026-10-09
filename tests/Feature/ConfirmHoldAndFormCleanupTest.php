<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Services\SimpleVaultService;
use App\Support\DualCurrency;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConfirmHoldAndFormCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Vault $vault;

    private Project $project;

    private SimpleVaultService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
        $this->project = Project::query()->create([
            'name' => 'Mayorca',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $this->service = app(SimpleVaultService::class);
    }

    public function test_confirm_job_pay_hold_before_unlock_pays_staff_not_available_cash(): void
    {
        $this->service->postAdvance([
            'amount' => 1000,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-09-01',
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
        ]);

        $staff = Staff::query()->create([
            'name' => 'Time Tom',
            'kind' => Staff::KIND_TIME,
            'trade' => 'laminate',
        ]);

        $line = $this->service->postJobPay([
            'staff_id' => $staff->id,
            'amount' => 200,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-10-05',
            'purpose' => 'Floor work',
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
        ]);

        $before = $this->service->balances(DualCurrency::USD, '2026-10-05', $this->vault);
        $this->assertSame(20.0, $before['staff_owed_held']);
        $availableBefore = $before['available_cash'];

        $this->actingAs($this->accountant)
            ->from(route('vault.job-pay.index'))
            ->post(route('vault.job-pay.confirm-hold', $line))
            ->assertRedirect(route('vault.job-pay.index'));

        $line->refresh();
        $this->assertNotNull($line->hold_released_at);

        $after = $this->service->balances(DualCurrency::USD, '2026-10-05', $this->vault);
        $this->assertSame(0.0, $after['staff_owed_held']);
        $this->assertSame($availableBefore, $after['available_cash']);

        $this->actingAs($this->accountant)
            ->get(route('vault.job-pay.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vault/Simple/JobPayIndex')
                ->where('lines.0.hold_open', false)
                ->where('canConfirmHold', true)
            );

        $this->actingAs($this->accountant)
            ->get(route('dashboards.vault'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('staff_holds', 0)
            );

        // Second confirm rejected
        $this->actingAs($this->accountant)
            ->post(route('vault.job-pay.confirm-hold', $line))
            ->assertSessionHasErrors('hold');
    }

    public function test_project_form_has_no_agreement_totals_block(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Projects/ProjectForm.jsx'));
        $this->assertStringNotContainsString('project_form_agreement_title', $source);
        $this->assertStringNotContainsString('AgreementCard', $source);
        $this->assertStringNotContainsString('PreviewStat', $source);

        $index = file_get_contents(resource_path('js/Pages/Projects/Index.jsx'));
        $this->assertStringNotContainsString("t('contract_value')", $index);
        $this->assertStringNotContainsString('contract_value_iqd', $index);
        $this->assertStringNotContainsString('projects_budget_iqd', $index);

        $admin = $this->userWithRole(Roles::SUPER_ADMIN);
        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'name' => 'No Agreement Site',
                'status' => Project::STATUS_PLANNING,
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'No Agreement Site')->first();
        $this->assertNotNull($project);
        $this->assertSame(0.0, (float) $project->total_budget_usd);
        $this->assertSame(0.0, (float) $project->contract_value_iqd);
    }

    public function test_estimate_card_uses_aligned_label_value_rows(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Dashboards/Vault.jsx'));
        $this->assertStringContainsString('flex items-baseline justify-between', $source);
        $this->assertStringContainsString('job_pay_hold_confirm', $source);
        $this->assertStringContainsString('vault.job-pay.confirm-hold', $source);
        // Unit-pay shortcut card removed from vault money forms (still available from staff profile).
        $this->assertStringNotContainsString('vault.lines.unit-pay.create', $source);
        $this->assertStringNotContainsString('vault_form_unit_pay_short', $source);
    }
}
