<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectReceipt;
use App\Models\Vault;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectFinancialPhase5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'https://open.er-api.com/*' => Http::response([
                'result' => 'success',
                'rates' => ['IQD' => 1310],
            ], 200),
        ]);

        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
    }

    public function test_project_show_includes_computed_financial_summary_for_boss(): void
    {
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $project = Project::query()->create([
            'name' => 'Erbil Site',
            'client' => 'Client Co',
            'contract_number' => 'C-1',
            'contract_value_iqd' => 200_000_000,
            'budget_iqd' => 150_000_000,
        ]);

        $this->actingAs($boss)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('canViewFinancials', true)
                ->where('canRecordReceipt', false)
                ->where('financialSummary.contract_value_iqd', 200_000_000)
                ->where('financialSummary.money_received_iqd', 0)
                ->where('financialSummary.project_expenses_iqd', 0)
                ->where('financialSummary.material_cost_iqd', 0)
                ->where('financialSummary.currency', 'IQD')
            );
    }

    public function test_accountant_can_record_money_received_and_boss_cannot(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $project = Project::query()->create([
            'name' => 'Pay Site',
            'contract_value_iqd' => 100_000_000,
        ]);

        $this->actingAs($boss)
            ->post(route('projects.receipts.store', $project), [
                'amount_iqd' => 5_000_000,
                'received_on' => now()->toDateString(),
                'source' => 'Cash',
            ])
            ->assertForbidden();

        $this->actingAs($accountant)
            ->post(route('projects.receipts.store', $project), [
                'amount_iqd' => 5_000_000,
                'received_on' => now()->toDateString(),
                'source' => 'Bank',
                'reference' => 'TRX-9',
                'notes' => 'First tranche',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_receipts', [
            'project_id' => $project->id,
            'amount_iqd' => 5_000_000,
            'reference' => 'TRX-9',
            'entered_by' => $accountant->id,
        ]);

        $this->assertSame(1, ProjectReceipt::query()->where('project_id', $project->id)->count());

        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first();
        $this->assertSame(0.0, (float) $vault->balance_usd); // IQD receipt does not invent USD
        $this->assertGreaterThan(0, (float) $vault->balance_iqd);

        $this->actingAs($accountant)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canRecordReceipt', true)
                ->where('financialSummary.money_received_iqd', 5_000_000)
                ->where('financialSummary.remaining_vs_contract_iqd', 95_000_000)
            );
    }

    public function test_stock_manager_cannot_see_projects_or_record_receipts(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $project = Project::query()->create([
            'name' => 'Hidden Finance',
            'contract_value_iqd' => 1_000_000,
        ]);

        $this->actingAs($stock)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($stock)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($stock)
            ->post(route('projects.receipts.store', $project), [
                'amount_iqd' => 1000,
                'received_on' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_project_create_accepts_financial_fields(): void
    {
        $admin = $this->userWithRole(Roles::SUPER_ADMIN);

        $response = $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'New Build',
            'client' => 'Gov Client',
            'location' => 'Sulaymaniyah',
            'contract_number' => 'GOV-55',
            'status' => Project::STATUS_ACTIVE,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'contract_value_iqd' => 750_000_000,
            'budget_iqd' => 600_000_000,
            'description' => 'Phase 5 fields',
        ]);

        $project = Project::query()->where('name', 'New Build')->first();
        $this->assertNotNull($project);
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame('Gov Client', $project->client);
        $this->assertSame('GOV-55', $project->contract_number);
        $this->assertSame('750000000.00', (string) $project->contract_value_iqd);
        $this->assertSame('600000000.00', (string) $project->budget_iqd);
    }

    public function test_project_index_includes_list_financial_summary_for_accountant(): void
    {
        $accountant = $this->userWithRole(Roles::ACCOUNTANT);
        Project::query()->create([
            'name' => 'Listed Site',
            'contract_value_iqd' => 10_000_000,
        ]);

        $this->actingAs($accountant)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('canViewFinancials', true)
                ->has('projects.0.financial_summary.contract_value_iqd')
                ->has('projects.0.financial_summary.money_received_usd')
                ->has('projects.0.financial_summary.money_received_iqd')
            );
    }
}
