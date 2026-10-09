<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Support\DualCurrency;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffPayTableAndProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $boss;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $this->boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
        $this->project = Project::query()->create([
            'name' => 'Mayorca',
            'status' => Project::STATUS_ACTIVE,
        ]);
    }

    public function test_job_pay_index_lists_only_job_pay_and_links_staff(): void
    {
        $time = Staff::query()->create([
            'name' => 'Time Tom',
            'kind' => Staff::KIND_TIME,
            'trade' => 'laminate',
            'phone' => '0750111',
        ]);
        $salary = Staff::query()->create([
            'name' => 'Salary Sara',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'admin',
            'monthly_salary' => 500,
            'currency' => DualCurrency::USD,
        ]);

        VaultLine::query()->create([
            'vault_id' => Vault::query()->first()->id,
            'kind' => VaultLine::KIND_JOB_PAY,
            'occurred_on' => '2026-10-01',
            'amount' => 200,
            'currency' => DualCurrency::USD,
            'project_id' => $this->project->id,
            'staff_id' => $time->id,
            'purpose' => 'Floor work',
            'hold_amount' => 20,
            'hold_pool' => VaultLine::HOLD_POOL_STAFF_OWED,
            'unlock_date' => '2027-03-30',
        ]);
        VaultLine::query()->create([
            'vault_id' => Vault::query()->first()->id,
            'kind' => VaultLine::KIND_SALARY,
            'occurred_on' => '2026-10-01',
            'amount' => 500,
            'currency' => DualCurrency::USD,
            'staff_id' => $salary->id,
            'hold_amount' => 0,
        ]);
        VaultLine::query()->create([
            'vault_id' => Vault::query()->first()->id,
            'kind' => VaultLine::KIND_EXPENSE,
            'occurred_on' => '2026-10-01',
            'amount' => 40,
            'currency' => DualCurrency::IQD,
            'staff_id' => $time->id,
            'hold_amount' => 0,
        ]);

        $this->actingAs($this->boss)
            ->get(route('vault.job-pay.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vault/Simple/JobPayIndex')
                ->has('lines', 1)
                ->where('lines.0.purpose', 'Floor work')
                ->where('lines.0.staff.name', 'Time Tom')
                ->where('lines.0.hold_amount', 20)
                ->where('lines.0.unlock_date', '2027-03-30')
                ->where('canCreate', false)
            );

        $this->actingAs($this->accountant)
            ->get(route('vault.job-pay.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreate', true)
            );

        $this->actingAs($this->boss)
            ->get(route('vault.job-pay.index'))
            ->assertOk();

        $empty = $this->actingAs($this->accountant)
            ->get(route('vault.job-pay.index'));
        // Source guard: empty CTA points at create form, not old advances.
        $source = file_get_contents(resource_path('js/Pages/Vault/Simple/JobPayIndex.jsx'));
        $this->assertStringContainsString("route('vault.lines.staff-pay.create'", $source);
        $this->assertStringNotContainsString('advances.index', $source);
        $this->assertStringNotContainsString('client-advances', $source);
        $empty->assertOk();
    }

    public function test_staff_index_and_profile_show_pay_history(): void
    {
        $time = Staff::query()->create([
            'name' => 'Time Tom',
            'kind' => Staff::KIND_TIME,
            'trade' => 'laminate',
            'phone' => '0750111',
        ]);
        $salary = Staff::query()->create([
            'name' => 'Salary Sara',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'admin',
            'monthly_salary' => 500,
            'currency' => DualCurrency::USD,
            'phone' => '0750222',
        ]);

        VaultLine::query()->create([
            'vault_id' => Vault::query()->first()->id,
            'kind' => VaultLine::KIND_JOB_PAY,
            'occurred_on' => '2026-10-02',
            'amount' => 100,
            'currency' => DualCurrency::IQD,
            'project_id' => $this->project->id,
            'staff_id' => $time->id,
            'purpose' => 'Patch',
            'hold_amount' => 10,
            'hold_pool' => VaultLine::HOLD_POOL_STAFF_OWED,
            'unlock_date' => '2027-03-31',
        ]);
        VaultLine::query()->create([
            'vault_id' => Vault::query()->first()->id,
            'kind' => VaultLine::KIND_SALARY,
            'occurred_on' => '2026-10-03',
            'amount' => 480,
            'currency' => DualCurrency::USD,
            'staff_id' => $salary->id,
            'purpose' => 'October',
            'hold_amount' => 0,
        ]);

        $this->actingAs($this->boss)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Index')
                ->has('staff', 2)
                ->where('canCreate', false)
            );

        $this->actingAs($this->accountant)
            ->get(route('staff.show', $time))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Show')
                ->where('staff.name', 'Time Tom')
                ->where('staff.kind', Staff::KIND_TIME)
                ->has('jobPays', 1)
                ->where('jobPays.0.purpose', 'Patch')
                ->has('salaries', 0)
                ->where('canCreateSalary', false)
            );

        $this->actingAs($this->accountant)
            ->get(route('staff.show', $salary))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Show')
                ->where('staff.monthly_salary', 500)
                ->where('staff.currency', DualCurrency::USD)
                ->has('jobPays', 0)
                ->has('salaries', 1)
                ->where('salaries.0.amount', 480)
                ->where('canCreateSalary', true)
            );

        $this->actingAs($this->userWithRole(Roles::STOCK_MANAGER))
            ->get(route('staff.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole(Roles::STOCK_MANAGER))
            ->get(route('vault.job-pay.index'))
            ->assertForbidden();
    }

    public function test_nav_includes_staff_pay_and_staff_for_ledger_roles(): void
    {
        $this->actingAs($this->boss)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav);

                    return $keys->contains('staffPay')
                        && $keys->contains('staff')
                        && $keys->contains('vault');
                })
            );

        $this->actingAs($this->userWithRole(Roles::STOCK_MANAGER))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.nav', function ($nav) {
                    $keys = collect($nav);

                    return ! $keys->contains('staffPay') && ! $keys->contains('staff');
                })
            );

        $ckb = json_decode(file_get_contents(lang_path('ckb.json')), true);
        $this->assertSame('پارەی ستاف', $ckb['vault_form_job_pay']);
    }
}
