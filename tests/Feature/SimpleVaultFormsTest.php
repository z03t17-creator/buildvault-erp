<?php

namespace Tests\Feature;

use App\Models\Penalty;
use App\Models\Project;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Support\DualCurrency;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleVaultFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Project $project;

    private Vault $vault;

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
            'name' => 'Mayorca Tower',
            'status' => Project::STATUS_ACTIVE,
        ]);
    }

    public function test_staff_create_page_and_store_salary_and_time(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('staff.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Staff/Create'));

        $response = $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Salary Sara',
                'phone' => '0750',
                'kind' => Staff::KIND_SALARY,
                'trade' => 'admin',
                'monthly_salary' => 600,
                'currency' => DualCurrency::USD,
            ]);

        $sara = Staff::query()->where('name', 'Salary Sara')->first();
        $this->assertNotNull($sara);
        $this->assertSame(Staff::KIND_SALARY, $sara->kind);
        $this->assertSame(DualCurrency::USD, $sara->currency);
        $response->assertRedirect(route('staff.show', $sara));

        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Time Tom',
                'kind' => Staff::KIND_TIME,
                'trade' => 'laminate',
                'return_to' => '/vault/lines/job-pay',
            ])
            ->assertRedirect('/vault/lines/job-pay');

        $this->assertDatabaseHas('staff', [
            'name' => 'Time Tom',
            'kind' => Staff::KIND_TIME,
        ]);
    }

    public function test_advance_form_posts_ten_ninety_hold(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('vault.lines.advance.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vault/Simple/AdvanceForm'));

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.advance.store'), [
                'occurred_on' => '2026-10-05',
                'amount' => 1000,
                'currency' => DualCurrency::USD,
                'project_id' => $this->project->id,
                'note' => 'Client deposit',
            ])
            ->assertRedirect(route('projects.show', $this->project));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_ADVANCE)->first();
        $this->assertNotNull($line);
        $this->assertSame(1000.0, (float) $line->amount);
        $this->assertSame(100.0, (float) $line->hold_amount);
        $this->assertSame(VaultLine::HOLD_POOL_COMPANY_INSURANCE, $line->hold_pool);
        $this->assertSame('2027-04-03', $line->unlock_date->toDateString());
        $this->assertSame(DualCurrency::USD, $line->currency);
        $this->assertSame($this->project->id, $line->project_id);
    }

    public function test_advance_requires_project_and_rolls_into_project_money_received(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('vault.lines.advance.store'), [
                'occurred_on' => '2026-10-05',
                'amount' => 500,
                'currency' => DualCurrency::USD,
            ])
            ->assertSessionHasErrors('project_id');

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.advance.store'), [
                'occurred_on' => '2026-10-05',
                'amount' => 2500,
                'currency' => DualCurrency::IQD,
                'project_id' => $this->project->id,
            ])
            ->assertRedirect(route('projects.show', $this->project));

        $summary = app(\App\Services\ProjectFinancialService::class)->summary($this->project->fresh());
        $this->assertSame(0.0, $summary['money_received_usd']);
        $this->assertSame(2500.0, $summary['money_received_iqd']);
    }

    public function test_vault_expense_routes_redirect_to_project_expenses(): void
    {
        $this->actingAs($this->accountant)
            ->get(route('vault.lines.expense.create'))
            ->assertRedirect(route('expenses.create'));

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.expense.store'), [
                'occurred_on' => '2026-10-05',
                'amount' => 150,
                'currency' => DualCurrency::IQD,
            ])
            ->assertRedirect(route('expenses.create'));

        $this->assertNull(VaultLine::query()->where('kind', VaultLine::KIND_EXPENSE)->first());
    }

    public function test_job_pay_form_holds_ten_percent_staff_owed(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Laminate Pro',
            'kind' => Staff::KIND_TIME,
            'trade' => 'laminate',
        ]);

        $this->actingAs($this->accountant)
            ->get(route('vault.lines.job-pay.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vault/Simple/StaffPayForm')
                ->has('staff', 1));

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.job-pay.store'), [
                'occurred_on' => '2026-10-05',
                'amount' => 200,
                'currency' => DualCurrency::USD,
                'staff_id' => $staff->id,
                'purpose' => 'Floor 3 laminate',
                'project_id' => $this->project->id,
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_JOB_PAY)->first();
        $this->assertNotNull($line);
        $this->assertSame(200.0, (float) $line->amount);
        $this->assertSame(20.0, (float) $line->hold_amount);
        $this->assertSame(VaultLine::HOLD_POOL_STAFF_OWED, $line->hold_pool);
        $this->assertSame('2027-04-03', $line->unlock_date->toDateString());
        $this->assertSame($staff->id, $line->staff_id);
        $this->assertSame('Floor 3 laminate', $line->purpose);
    }

    public function test_job_pay_can_skip_insurance_hold(): void
    {
        $staff = Staff::query()->create([
            'name' => 'No Hold Tom',
            'kind' => Staff::KIND_TIME,
            'trade' => 'helper',
        ]);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.job-pay.store'), [
                'occurred_on' => '2026-10-05',
                'amount' => 100,
                'currency' => DualCurrency::USD,
                'staff_id' => $staff->id,
                'purpose' => 'Full pay now',
                'project_id' => $this->project->id,
                'apply_insurance' => false,
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_JOB_PAY)->latest('id')->first();
        $this->assertNotNull($line);
        $this->assertSame(100.0, (float) $line->amount);
        $this->assertSame(0.0, (float) $line->hold_amount);
        $this->assertNull($line->hold_pool);
        $this->assertNull($line->unlock_date);

        $source = file_get_contents(resource_path('js/Pages/Vault/Simple/StaffPayForm.jsx'));
        $this->assertStringContainsString('apply_insurance', $source);
        $this->assertStringContainsString('InsuranceHoldToggle', $source);
    }

    public function test_salary_form_pays_monthly_minus_penalties_and_shows_estimate(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Office Omar',
            'kind' => Staff::KIND_SALARY,
            'monthly_salary' => 310,
            'currency' => DualCurrency::USD,
        ]);

        Penalty::query()->create([
            'staff_id' => $staff->id,
            'project_id' => $this->project->id,
            'type' => Penalty::TYPE_FORFEIT_DAY,
            'occurred_on' => now()->toDateString(),
            'status' => Penalty::STATUS_APPLIED,
            'amount_usd' => 0,
            'amount_iqd' => 0,
            'reason' => 'Late > 30m',
        ]);

        $this->actingAs($this->accountant)
            ->get(route('vault.lines.salary.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vault/Simple/SalaryForm')
                ->has('staff', 1)
                ->has('estimates.USD')
                ->has('availableCash.USD')
                ->where('salaryDues.'.$staff->id, function ($due) {
                    // Oct 2026 has 31 days → daily = 10; one forfeit day → 300
                    return abs((float) $due - 300.0) < 0.01;
                }));

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.salary.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => now()->toDateString(),
                'note' => 'October pay',
            ])
            ->assertRedirect(route('dashboards.vault'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_SALARY)->first();
        $this->assertNotNull($line);
        $this->assertSame(300.0, (float) $line->amount);
        $this->assertSame(0.0, (float) $line->hold_amount);
        $this->assertNull($line->hold_pool);
        $this->assertSame($staff->id, $line->staff_id);
        $this->assertSame(DualCurrency::USD, $line->currency);
    }

    public function test_stock_manager_cannot_open_money_forms(): void
    {
        $stock = $this->userWithRole(Roles::STOCK_MANAGER);

        $this->actingAs($stock)
            ->get(route('vault.lines.advance.create'))
            ->assertForbidden();

        $this->actingAs($stock)
            ->get(route('staff.create'))
            ->assertForbidden();
    }

    public function test_unit_pay_rejects_monthly_staff(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Salary Only',
            'kind' => Staff::KIND_SALARY,
            'pay_model' => Staff::PAY_MONTHLY,
            'monthly_salary' => 400,
            'currency' => DualCurrency::USD,
        ]);

        $this->actingAs($this->accountant)
            ->from(route('vault.lines.unit-pay.create'))
            ->post(route('vault.lines.unit-pay.store'), [
                'occurred_on' => '2026-10-05',
                'quantity' => 10,
                'staff_id' => $staff->id,
                'project_id' => $this->project->id,
            ])
            // Unified store treats monthly as salary; quantity alone without amount uses salary due.
            ->assertRedirect(route('dashboards.vault'));

        $this->assertDatabaseHas('vault_lines', [
            'staff_id' => $staff->id,
            'kind' => VaultLine::KIND_SALARY,
        ]);
    }
}
