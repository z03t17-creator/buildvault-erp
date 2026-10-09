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

class StaffUnitRatePayTest extends TestCase
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

    public function test_create_unit_staff_and_pay_by_quantity_times_rate(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Laminate Karwan',
                'phone' => '0750999',
                'kind' => Staff::KIND_UNIT,
                'trade' => 'laminate',
                'unit_rate' => 5,
                'rate_unit' => 'm²',
                'currency' => DualCurrency::USD,
            ])
            ->assertRedirect();

        $staff = Staff::query()->where('name', 'Laminate Karwan')->first();
        $this->assertNotNull($staff);
        $this->assertTrue($staff->isUnit());
        $this->assertSame(5.0, (float) $staff->unit_rate);
        $this->assertSame('m²', $staff->rate_unit);
        $this->assertSame(600.0, $staff->amountForQuantity(120));

        $this->service->postAdvance([
            'amount' => 2000,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-10-01',
            'project_id' => $this->project->id,
            'vault_id' => $this->vault->id,
        ]);

        $this->actingAs($this->accountant)
            ->get(route('vault.lines.unit-pay.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vault/Simple/StaffPayForm')
                ->has('staff', 1)
            );

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.unit-pay.store'), [
                'occurred_on' => '2026-10-05',
                'quantity' => 120,
                'staff_id' => $staff->id,
                'project_id' => $this->project->id,
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_UNIT_PAY)->first();
        $this->assertNotNull($line);
        $this->assertSame(600.0, (float) $line->amount);
        $this->assertSame(60.0, (float) $line->hold_amount);
        $this->assertSame(DualCurrency::USD, $line->currency);
        $this->assertSame($staff->id, $line->staff_id);

        $balances = $this->service->balances(DualCurrency::USD, '2026-10-05', $this->vault);
        // advance 1800 available + 200 company hold; unit pay removes 540 from available, 60 staff owed
        $this->assertSame(1260.0, $balances['available_cash']);
        $this->assertSame(60.0, $balances['staff_owed_held']);

        $this->actingAs($this->accountant)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('staff.kind', Staff::KIND_UNIT)
                ->where('staff.unit_rate', 5)
                ->where('staff.rate_unit', 'm²')
                ->has('jobPays', 1)
                ->where('canCreateUnitPay', true)
            );
        $this->actingAs($this->accountant)
            ->post(route('vault.lines.unit-pay.store'), [
                'occurred_on' => '2026-10-06',
                'quantity' => 10,
                'staff_id' => $staff->id,
                'project_id' => $this->project->id,
                'apply_insurance' => false,
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $noHold = VaultLine::query()
            ->where('kind', VaultLine::KIND_UNIT_PAY)
            ->where('staff_id', $staff->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($noHold);
        $this->assertSame(50.0, (float) $noHold->amount); // 10 × 5
        $this->assertSame(0.0, (float) $noHold->hold_amount);
        $this->assertNull($noHold->hold_pool);
        $this->assertNull($noHold->unlock_date);
    }

    public function test_staff_create_offers_rate_unit_suggestions_and_saves_custom_unit(): void
    {
        Staff::query()->create([
            'name' => 'Existing Unit',
            'kind' => Staff::KIND_UNIT,
            'trade' => 'tile',
            'unit_rate' => 3,
            'rate_unit' => 'کاشی',
            'currency' => DualCurrency::IQD,
        ]);

        $this->actingAs($this->accountant)
            ->get(route('staff.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Create')
                ->has('rateUnitSuggestions')
                ->where('rateUnitSuggestions', function ($units) {
                    $list = collect($units)->all();

                    return in_array('m²', $list, true)
                        && in_array('دانە', $list, true)
                        && in_array('ڤێلا', $list, true)
                        && in_array('ڕۆژ', $list, true)
                        && in_array('مانگ', $list, true)
                        && in_array('کاشی', $list, true);
                })
            );

        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Villa Crew',
                'kind' => Staff::KIND_UNIT,
                'trade' => 'finish',
                'unit_rate' => 150,
                'rate_unit' => 'ڤێلا',
                'currency' => DualCurrency::USD,
            ])
            ->assertRedirect();

        $villa = Staff::query()->where('name', 'Villa Crew')->first();
        $this->assertNotNull($villa);
        $this->assertSame('ڤێلا', $villa->rate_unit);

        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Custom Unit Person',
                'kind' => Staff::KIND_UNIT,
                'unit_rate' => 12.5,
                'rate_unit' => 'بۆکس',
                'currency' => DualCurrency::IQD,
            ])
            ->assertRedirect();

        $custom = Staff::query()->where('name', 'Custom Unit Person')->first();
        $this->assertNotNull($custom);
        $this->assertSame('بۆکس', $custom->rate_unit);

        $suggestions = Staff::suggestedRateUnits();
        $this->assertContains('بۆکس', $suggestions);
        $this->assertSame('m²', $suggestions[0]);
    }

    public function test_unit_pay_rejects_time_staff(): void
    {
        $time = Staff::query()->create([
            'name' => 'Time Only',
            'kind' => Staff::KIND_TIME,
            'trade' => 'helper',
        ]);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.unit-pay.store'), [
                'occurred_on' => '2026-10-05',
                'quantity' => 10,
                'staff_id' => $time->id,
                'project_id' => $this->project->id,
            ])
            ->assertSessionHasErrors('amount');
    }
}
