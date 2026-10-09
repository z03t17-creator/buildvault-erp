<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffRate;
use App\Models\User;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Models\VaultLineItem;
use App\Services\SimpleVaultService;
use App\Support\DualCurrency;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffPayRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $stock;

    private Vault $vault;

    private SimpleVaultService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = $this->userWithRole(Roles::ACCOUNTANT);
        $this->stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 0,
        ]);
        $this->service = app(SimpleVaultService::class);
        $this->service->postAdvance([
            'amount' => 5_000_000,
            'currency' => DualCurrency::IQD,
            'occurred_on' => '2026-10-01',
            'vault_id' => $this->vault->id,
        ]);
    }

    public function test_unit_staff_stores_per_person_rates(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Hunar',
                'phone' => '0750',
                'role' => 'دەرگاچیی',
                'pay_model' => Staff::PAY_UNIT,
                'rates' => [
                    ['item_name' => 'دەرگای MDF', 'unit' => 'دانە', 'rate' => 20000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای چوونەژوورەوە', 'unit' => 'دانە', 'rate' => 25000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای ناوەوە', 'unit' => 'دانە', 'rate' => 18000, 'currency' => DualCurrency::IQD],
                ],
            ])
            ->assertRedirect();

        $hunar = Staff::query()->where('name', 'Hunar')->first();
        $this->assertNotNull($hunar);
        $this->assertTrue($hunar->isUnit());
        $this->assertSame('دەرگاچیی', $hunar->role);
        $this->assertCount(3, $hunar->rates);
        $this->assertSame(25000.0, (float) $hunar->rates->firstWhere('item_name', 'دەرگای چوونەژوورەوە')->rate);

        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Aland',
                'pay_model' => Staff::PAY_UNIT,
                'role' => 'دەرگاچیی',
                'rates' => [
                    ['item_name' => 'دەرگای چوونەژوورەوە', 'unit' => 'دانە', 'rate' => 15000, 'currency' => DualCurrency::IQD],
                ],
            ])
            ->assertRedirect();

        $aland = Staff::query()->where('name', 'Aland')->first();
        $this->assertCount(1, $aland->rates);
        $this->assertSame(15000.0, (float) $aland->rates->first()->rate);
        $this->assertNotEquals(
            (float) $hunar->rates->firstWhere('item_name', 'دەرگای چوونەژوورەوە')->rate,
            (float) $aland->rates->first()->rate,
        );
    }

    public function test_daily_and_monthly_staff_create(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Daily Dana',
                'pay_model' => Staff::PAY_DAILY,
                'role' => 'Painter',
                'day_rate' => 40000,
                'currency' => DualCurrency::IQD,
            ])
            ->assertRedirect();

        $daily = Staff::query()->where('name', 'Daily Dana')->first();
        $this->assertTrue($daily->isDaily());
        $this->assertSame(40000.0, (float) $daily->day_rate);

        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Monthly Mina',
                'pay_model' => Staff::PAY_MONTHLY,
                'role' => 'admin',
                'monthly_salary' => 600,
                'currency' => DualCurrency::USD,
            ])
            ->assertRedirect();

        $monthly = Staff::query()->where('name', 'Monthly Mina')->first();
        $this->assertTrue($monthly->isMonthly());
        $this->assertSame(600.0, (float) $monthly->monthly_salary);
    }

    public function test_unit_pay_line_math_location_and_items(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Hunar Pay',
            'kind' => Staff::KIND_UNIT,
            'pay_model' => Staff::PAY_UNIT,
            'role' => 'دەرگاچیی',
            'currency' => DualCurrency::IQD,
        ]);
        $mdf = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'دەرگای MDF',
            'unit' => 'دانە',
            'rate' => 20000,
            'currency' => DualCurrency::IQD,
            'sort_order' => 0,
        ]);
        $entrance = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'Entrance',
            'unit' => 'دانە',
            'rate' => 25000,
            'currency' => DualCurrency::IQD,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->accountant)
            ->get(route('vault.lines.staff-pay.create', ['staff_id' => $staff->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vault/Simple/StaffPayForm')
                ->where('preselectStaffId', $staff->id)
                ->where('canEditRate', true)
            );

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-05',
                'site_kind' => VaultLine::SITE_BUILDING,
                'block' => 'A1',
                'zone' => 'Z2',
                'floor' => '3',
                'apartment_number' => '12',
                'apartment_model' => 'B',
                'apply_insurance' => true,
                'items' => [
                    [
                        'staff_rate_id' => $mdf->id,
                        'item_name' => $mdf->item_name,
                        'unit' => $mdf->unit,
                        'quantity' => 2,
                        'unit_rate' => 20000,
                        'currency' => DualCurrency::IQD,
                    ],
                    [
                        'staff_rate_id' => $entrance->id,
                        'item_name' => $entrance->item_name,
                        'unit' => $entrance->unit,
                        'quantity' => 1,
                        'unit_rate' => 25000,
                        'currency' => DualCurrency::IQD,
                    ],
                ],
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_UNIT_PAY)->first();
        $this->assertNotNull($line);
        $this->assertSame(65000.0, (float) $line->amount); // 2*20k + 1*25k
        $this->assertSame(6500.0, (float) $line->hold_amount);
        $this->assertSame(VaultLine::HOLD_POOL_STAFF_OWED, $line->hold_pool);
        $this->assertSame('2027-04-03', $line->unlock_date->toDateString());
        $this->assertSame(VaultLine::SITE_BUILDING, $line->site_kind);
        $this->assertSame('A1', $line->block);
        $this->assertSame('Z2', $line->zone);
        $this->assertSame('3', $line->floor);
        $this->assertSame('12', $line->apartment_number);
        $this->assertSame('B', $line->apartment_model);
        $this->assertCount(2, $line->items);
        $this->assertEqualsWithDelta(40000.0, (float) $line->items->sum('subtotal') - 25000.0, 0.01);
        $this->assertSame(65000.0, (float) VaultLineItem::query()->where('vault_line_id', $line->id)->sum('subtotal'));

        $balances = $this->service->balances(DualCurrency::IQD, '2026-10-05', $this->vault);
        // advance: 4.5M available + 0.5M company; unit pay leaves 58500, holds 6500
        $this->assertSame(4_500_000 - 58_500.0, $balances['available_cash']);
        $this->assertSame(6500.0, $balances['staff_owed_held']);
    }

    public function test_unit_pay_insurance_off_pays_full_amount(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Daban',
            'kind' => Staff::KIND_UNIT,
            'pay_model' => Staff::PAY_UNIT,
            'currency' => DualCurrency::IQD,
        ]);
        $rate = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'دەرگای MDF',
            'unit' => 'دانە',
            'rate' => 10000,
            'currency' => DualCurrency::IQD,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-06',
                'site_kind' => VaultLine::SITE_VILLA,
                'villa_number' => '7',
                'zone' => 'North',
                'area' => '120m',
                'apply_insurance' => false,
                'items' => [[
                    'staff_rate_id' => $rate->id,
                    'item_name' => $rate->item_name,
                    'unit' => $rate->unit,
                    'quantity' => 3,
                    'unit_rate' => 10000,
                    'currency' => DualCurrency::IQD,
                ]],
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_UNIT_PAY)->latest('id')->first();
        $this->assertSame(30000.0, (float) $line->amount);
        $this->assertSame(0.0, (float) $line->hold_amount);
        $this->assertNull($line->hold_pool);
        $this->assertNull($line->unlock_date);
        $this->assertSame(VaultLine::SITE_VILLA, $line->site_kind);
        $this->assertSame('7', $line->villa_number);
        $this->assertSame('North', $line->zone);
        $this->assertSame('120m', $line->area);
    }

    public function test_daily_pay_defaults_insurance_on(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Day Worker',
            'kind' => Staff::KIND_TIME,
            'pay_model' => Staff::PAY_DAILY,
            'day_rate' => 50000,
            'currency' => DualCurrency::IQD,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-07',
                'days_count' => 2,
                'day_rate' => 50000,
                'currency' => DualCurrency::IQD,
                'site_kind' => VaultLine::SITE_VILLA,
                'villa_number' => '3',
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_DAILY_PAY)->first();
        $this->assertNotNull($line);
        $this->assertSame(100000.0, (float) $line->amount);
        $this->assertSame(10000.0, (float) $line->hold_amount);
        $this->assertSame(2.0, (float) $line->days_count);
        $this->assertSame(50000.0, (float) $line->day_rate);
        $this->assertSame(VaultLine::HOLD_POOL_STAFF_OWED, $line->hold_pool);
    }

    public function test_monthly_pay_defaults_insurance_off(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Office',
            'kind' => Staff::KIND_SALARY,
            'pay_model' => Staff::PAY_MONTHLY,
            'monthly_salary' => 500,
            'currency' => DualCurrency::USD,
        ]);

        $this->service->postAdvance([
            'amount' => 2000,
            'currency' => DualCurrency::USD,
            'occurred_on' => '2026-10-01',
            'vault_id' => $this->vault->id,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-15',
                'amount' => 500,
            ])
            ->assertRedirect(route('dashboards.vault'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_SALARY)->first();
        $this->assertNotNull($line);
        $this->assertSame(500.0, (float) $line->amount);
        $this->assertSame(0.0, (float) $line->hold_amount);
        $this->assertNull($line->hold_pool);
    }

    public function test_locked_rate_user_cannot_override_unit_rate(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Locked Rates',
            'kind' => Staff::KIND_UNIT,
            'pay_model' => Staff::PAY_UNIT,
            'currency' => DualCurrency::IQD,
        ]);
        $rate = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'MDF',
            'unit' => 'دانە',
            'rate' => 10000,
            'currency' => DualCurrency::IQD,
        ]);

        // Stock manager cannot manage ledger at all.
        $this->actingAs($this->stock)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-08',
                'items' => [[
                    'staff_rate_id' => $rate->id,
                    'item_name' => 'MDF',
                    'unit' => 'دانە',
                    'quantity' => 1,
                    'unit_rate' => 99999,
                    'currency' => DualCurrency::IQD,
                ]],
            ])
            ->assertForbidden();

        // Accountant can edit rate (admin/boss/accountant).
        $this->actingAs($this->accountant)
            ->get(route('vault.lines.staff-pay.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canEditRate', true));
    }

    public function test_staff_profile_lists_rates_and_pay_link(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Profile Unit',
            'kind' => Staff::KIND_UNIT,
            'pay_model' => Staff::PAY_UNIT,
            'role' => 'Painter',
            'currency' => DualCurrency::IQD,
        ]);
        StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'Wall',
            'unit' => 'm²',
            'rate' => 5,
            'currency' => DualCurrency::USD,
        ]);

        $this->actingAs($this->accountant)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Show')
                ->where('staff.pay_model', Staff::PAY_UNIT)
                ->has('rates', 1)
                ->where('canCreateUnitPay', true)
                ->where('canPay', true)
            );

        $source = file_get_contents(resource_path('js/Pages/Staff/Show.jsx'));
        $this->assertStringContainsString("route('vault.lines.staff-pay.create'", $source);
        $this->assertStringContainsString('staff_rates_title', $source);
    }

    public function test_vault_home_has_no_fifth_unit_card(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Dashboards/Vault.jsx'));
        $this->assertStringNotContainsString('vault.lines.unit-pay.create', $source);
        $this->assertStringContainsString('vault.lines.job-pay.create', $source);
        $this->assertStringContainsString('vault_form_job_pay', $source);
        $this->assertStringContainsString('vault_form_salary', $source);
        $this->assertStringContainsString('vault_form_advance', $source);
        $this->assertStringContainsString('vault_form_expense', $source);
    }

    public function test_currencies_never_blend_on_unit_rows(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Blend Block',
            'kind' => Staff::KIND_UNIT,
            'pay_model' => Staff::PAY_UNIT,
        ]);
        $a = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'A',
            'unit' => 'دانە',
            'rate' => 10,
            'currency' => DualCurrency::USD,
        ]);
        $b = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'B',
            'unit' => 'دانە',
            'rate' => 1000,
            'currency' => DualCurrency::IQD,
        ]);

        $this->actingAs($this->accountant)
            ->from(route('vault.lines.staff-pay.create'))
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-09',
                'items' => [
                    [
                        'staff_rate_id' => $a->id,
                        'item_name' => 'A',
                        'unit' => 'دانە',
                        'quantity' => 1,
                        'unit_rate' => 10,
                        'currency' => DualCurrency::USD,
                    ],
                    [
                        'staff_rate_id' => $b->id,
                        'item_name' => 'B',
                        'unit' => 'دانە',
                        'quantity' => 1,
                        'unit_rate' => 1000,
                        'currency' => DualCurrency::IQD,
                    ],
                ],
            ])
            ->assertRedirect(route('vault.lines.staff-pay.create'))
            ->assertSessionHasErrors();
    }
}
