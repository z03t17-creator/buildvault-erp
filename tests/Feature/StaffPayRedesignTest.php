<?php

namespace Tests\Feature;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\Project;
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

    public function test_staff_create_without_required_rates(): void
    {
        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Daily No Rate',
                'pay_model' => Staff::PAY_DAILY,
                'role' => 'Painter',
            ])
            ->assertRedirect();

        $daily = Staff::query()->where('name', 'Daily No Rate')->first();
        $this->assertNotNull($daily);
        $this->assertTrue($daily->isDaily());
        $this->assertNull($daily->day_rate);

        $this->actingAs($this->accountant)
            ->post(route('staff.store'), [
                'name' => 'Unit No Rates',
                'pay_model' => Staff::PAY_UNIT,
                'role' => 'دەرگاچیی',
            ])
            ->assertRedirect();

        $unit = Staff::query()->where('name', 'Unit No Rates')->first();
        $this->assertNotNull($unit);
        $this->assertTrue($unit->isUnit());
        $this->assertSame(0, $unit->rates()->count());
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

    public function test_daily_pay_records_day_rate_and_transport_on_job(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Transport Dana',
            'kind' => Staff::KIND_TIME,
            'pay_model' => Staff::PAY_DAILY,
            'currency' => DualCurrency::IQD,
        ]);
        $project = Project::query()->create([
            'name' => 'Villa North',
            'status' => 'active',
        ]);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-08',
                'project_id' => $project->id,
                'days_count' => 3,
                'day_rate' => 40000,
                'transport_amount' => 15000,
                'currency' => DualCurrency::IQD,
                'site_kind' => VaultLine::SITE_VILLA,
                'villa_number' => '12',
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_DAILY_PAY)->latest('id')->first();
        $this->assertNotNull($line);
        $this->assertSame(135000.0, (float) $line->amount);
        $this->assertSame(12000.0, (float) $line->hold_amount);
        $this->assertSame(3.0, (float) $line->days_count);
        $this->assertSame(40000.0, (float) $line->day_rate);
        $this->assertSame(15000.0, (float) $line->transport_amount);
        $this->assertNull($staff->fresh()->day_rate);
    }

    public function test_unit_pay_accepts_free_piece_prices_on_job(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Piece Worker',
            'kind' => Staff::KIND_UNIT,
            'pay_model' => Staff::PAY_UNIT,
        ]);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-08',
                'apply_insurance' => true,
                'items' => [
                    [
                        'item_name' => 'دەرگای MDF',
                        'unit' => 'دانە',
                        'quantity' => 2,
                        'unit_rate' => 20000,
                        'currency' => DualCurrency::IQD,
                    ],
                    [
                        'item_name' => 'لامێنێت',
                        'unit' => 'm²',
                        'quantity' => 10,
                        'unit_rate' => 5000,
                        'currency' => DualCurrency::IQD,
                    ],
                ],
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $line = VaultLine::query()->where('kind', VaultLine::KIND_UNIT_PAY)->latest('id')->first();
        $this->assertNotNull($line);
        $this->assertSame(90000.0, (float) $line->amount);
        $this->assertSame(9000.0, (float) $line->hold_amount);
        $this->assertCount(2, $line->items);
        $this->assertNull($line->items->first()->staff_rate_id);
        $this->assertSame('دەرگای MDF', $line->items->first()->item_name);
        $this->assertSame(0, $staff->rates()->count());
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
                ->where('canEdit', true)
            );

        $source = file_get_contents(resource_path('js/Pages/Staff/Show.jsx'));
        $this->assertStringContainsString("route('vault.lines.staff-pay.create'", $source);
        $this->assertStringContainsString("route('staff.edit'", $source);
        $this->assertStringContainsString('staff_rates_title', $source);
    }

    public function test_staff_screens_use_the_desk_layout(): void
    {
        foreach ([
            'js/Pages/Staff/Index.jsx',
            'js/Pages/Staff/Show.jsx',
            'js/Pages/Staff/Create.jsx',
            'js/Pages/Vault/Simple/StaffPayForm.jsx',
        ] as $path) {
            $source = file_get_contents(resource_path($path));
            $this->assertStringContainsString('desk', $source);
            $this->assertStringContainsString('AuthenticatedLayout', $source);
        }

        $layout = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.jsx'));
        $this->assertStringContainsString("desk ? 'bv-desk '", $layout);
        $this->assertStringNotContainsString("desk ? 'dark bv-desk '", $layout);
    }

    public function test_default_catalog_includes_shaft_door_and_square_meters(): void
    {
        $items = Staff::suggestedItemNames();
        $this->assertContains('دەرگای شافت', $items);
        $this->assertContains('Shaft door', $items);
        $this->assertContains('ڕووبەر', $items);
        $this->assertContains('m²', $items);

        $units = Staff::suggestedRateUnits();
        $this->assertContains('m²', $units);
        $this->assertContains('دانە', $units);
        $this->assertSame('m²', $units[0]);

        $this->actingAs($this->accountant)
            ->get(route('staff.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Create')
                ->where('itemSuggestions', function ($list) {
                    $names = collect($list)->all();

                    return in_array('دەرگای شافت', $names, true)
                        && in_array('Shaft door', $names, true)
                        && in_array('m²', $names, true);
                })
                ->where('rateUnitSuggestions', function ($list) {
                    $names = collect($list)->all();

                    return in_array('m²', $names, true)
                        && in_array('دانە', $names, true)
                        && $names[0] === 'm²';
                })
            );
    }

    public function test_staff_edit_replaces_name_role_pay_model_and_rates(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Hunar',
            'phone' => '0750',
            'role' => 'دەرگاچیی',
            'pay_model' => Staff::PAY_UNIT,
            'kind' => Staff::KIND_UNIT,
            'currency' => DualCurrency::IQD,
        ]);
        $old = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'دەرگای چوونەژوورەوە',
            'unit' => 'دانە',
            'rate' => 25000,
            'currency' => DualCurrency::IQD,
            'sort_order' => 0,
        ]);
        StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'دەرگای ناوەوە',
            'unit' => 'دانە',
            'rate' => 18000,
            'currency' => DualCurrency::IQD,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->accountant)
            ->get(route('staff.edit', $staff))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Create')
                ->where('staff.id', $staff->id)
                ->where('staff.name', 'Hunar')
                ->has('staff.rates', 2)
            );

        $this->actingAs($this->stock)
            ->get(route('staff.edit', $staff))
            ->assertForbidden();

        $this->actingAs($this->stock)
            ->put(route('staff.update', $staff), [
                'name' => 'Nope',
                'pay_model' => Staff::PAY_DAILY,
                'day_rate' => 1,
                'currency' => DualCurrency::IQD,
            ])
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->put(route('staff.update', $staff), [
                'name' => 'Wasta Hunar',
                'phone' => '07501112233',
                'role' => 'Door carpenter',
                'pay_model' => Staff::PAY_UNIT,
                'rates' => [
                    ['item_name' => 'دەرگای MDF', 'unit' => 'دانە', 'rate' => 20000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای چوونەژوورەوە', 'unit' => 'دانە', 'rate' => 25000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای شافت', 'unit' => 'دانە', 'rate' => 8000, 'currency' => DualCurrency::IQD],
                ],
            ])
            ->assertRedirect(route('staff.show', $staff));

        $staff->refresh();
        $this->assertSame('Wasta Hunar', $staff->name);
        $this->assertSame('07501112233', $staff->phone);
        $this->assertSame('Door carpenter', $staff->role);
        $this->assertTrue($staff->isUnit());
        $this->assertNull(StaffRate::query()->find($old->id));
        $this->assertCount(3, $staff->rates);
        $this->assertSame(8000.0, (float) $staff->rates->firstWhere('item_name', 'دەرگای شافت')->rate);

        $this->actingAs($this->accountant)
            ->put(route('staff.update', $staff), [
                'name' => 'Wasta Hunar',
                'phone' => '07501112233',
                'role' => 'Painter',
                'pay_model' => Staff::PAY_DAILY,
                'day_rate' => 45000,
                'currency' => DualCurrency::IQD,
            ])
            ->assertRedirect(route('staff.show', $staff));

        $staff->refresh();
        $this->assertTrue($staff->isDaily());
        $this->assertSame(45000.0, (float) $staff->day_rate);
        $this->assertSame(0, $staff->rates()->count());
        $this->assertNull($staff->monthly_salary);

        $this->actingAs($this->accountant)
            ->put(route('staff.update', $staff), [
                'name' => 'Wasta Hunar',
                'role' => 'Office',
                'pay_model' => Staff::PAY_MONTHLY,
                'monthly_salary' => 700,
                'currency' => DualCurrency::USD,
            ])
            ->assertRedirect(route('staff.show', $staff));

        $staff->refresh();
        $this->assertTrue($staff->isMonthly());
        $this->assertSame(700.0, (float) $staff->monthly_salary);
        $this->assertSame(DualCurrency::USD, $staff->currency);
        $this->assertNull($staff->day_rate);
    }

    public function test_edited_shaft_rate_pays_on_building_and_villa(): void
    {
        $hunar = Staff::query()->create([
            'name' => 'Hunar',
            'role' => 'دەرگاچیی',
            'pay_model' => Staff::PAY_UNIT,
            'kind' => Staff::KIND_UNIT,
            'currency' => DualCurrency::IQD,
        ]);
        StaffRate::query()->create([
            'staff_id' => $hunar->id,
            'item_name' => 'دەرگای چوونەژوورەوە',
            'unit' => 'دانە',
            'rate' => 25000,
            'currency' => DualCurrency::IQD,
            'sort_order' => 0,
        ]);

        $aland = Staff::query()->create([
            'name' => 'Aland',
            'role' => 'دەرگاچیی',
            'pay_model' => Staff::PAY_UNIT,
            'kind' => Staff::KIND_UNIT,
            'currency' => DualCurrency::IQD,
        ]);
        StaffRate::query()->create([
            'staff_id' => $aland->id,
            'item_name' => 'دەرگای چوونەژوورەوە',
            'unit' => 'دانە',
            'rate' => 20000,
            'currency' => DualCurrency::IQD,
            'sort_order' => 0,
        ]);

        $daban = Staff::query()->create([
            'name' => 'Daban',
            'role' => 'دەرگاچیی',
            'pay_model' => Staff::PAY_UNIT,
            'kind' => Staff::KIND_UNIT,
            'currency' => DualCurrency::IQD,
        ]);
        StaffRate::query()->create([
            'staff_id' => $daban->id,
            'item_name' => 'دەرگای MDF',
            'unit' => 'دانە',
            'rate' => 20000,
            'currency' => DualCurrency::IQD,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->accountant)
            ->put(route('staff.update', $hunar), [
                'name' => 'Wasta Hunar',
                'role' => 'دەرگاچیی',
                'pay_model' => Staff::PAY_UNIT,
                'rates' => [
                    ['item_name' => 'دەرگای MDF', 'unit' => 'دانە', 'rate' => 20000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای چوونەژوورەوە', 'unit' => 'دانە', 'rate' => 25000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای شافت', 'unit' => 'دانە', 'rate' => 8000, 'currency' => DualCurrency::IQD],
                ],
            ])
            ->assertRedirect(route('staff.show', $hunar));

        $this->actingAs($this->accountant)
            ->put(route('staff.update', $aland), [
                'name' => 'Wasta Aland',
                'role' => 'دەرگاچیی',
                'pay_model' => Staff::PAY_UNIT,
                'rates' => [
                    ['item_name' => 'دەرگای MDF', 'unit' => 'دانە', 'rate' => 20000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای چوونەژوورەوە', 'unit' => 'دانە', 'rate' => 15000, 'currency' => DualCurrency::IQD],
                    ['item_name' => 'دەرگای شافت', 'unit' => 'دانە', 'rate' => 8000, 'currency' => DualCurrency::IQD],
                ],
            ])
            ->assertRedirect(route('staff.show', $aland));

        $hunar->refresh();
        $aland->refresh();
        $daban->refresh();
        $shaft = $hunar->rates->firstWhere('item_name', 'دەرگای شافت');
        $this->assertNotNull($shaft);
        $this->assertSame(8000.0, (float) $shaft->rate);
        $this->assertSame(25000.0, (float) $hunar->rates->firstWhere('item_name', 'دەرگای چوونەژوورەوە')->rate);
        $this->assertSame(15000.0, (float) $aland->rates->firstWhere('item_name', 'دەرگای چوونەژوورەوە')->rate);
        $this->assertCount(1, $daban->rates);
        $this->assertSame('دەرگای MDF', $daban->rates->first()->item_name);

        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $hunar->id,
                'occurred_on' => '2026-10-11',
                'site_kind' => VaultLine::SITE_BUILDING,
                'block' => 'A1',
                'zone' => 'Z2',
                'floor' => '3',
                'apartment_number' => '12',
                'apartment_model' => 'B',
                'apply_insurance' => true,
                'items' => [[
                    'staff_rate_id' => $shaft->id,
                    'item_name' => $shaft->item_name,
                    'unit' => 'دانە',
                    'quantity' => 2,
                    'unit_rate' => 8000,
                    'currency' => DualCurrency::IQD,
                ]],
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $building = VaultLine::query()->where('site_kind', VaultLine::SITE_BUILDING)->first();
        $this->assertNotNull($building);
        $this->assertSame(16000.0, (float) $building->amount);
        $this->assertSame(1600.0, (float) $building->hold_amount);
        $this->assertSame('A1', $building->block);
        $this->assertSame('Z2', $building->zone);
        $this->assertSame('3', $building->floor);
        $this->assertSame('12', $building->apartment_number);
        $this->assertSame('B', $building->apartment_model);
        $this->assertSame('دەرگای شافت', $building->items->first()->item_name);
        $this->assertSame(16000.0, (float) $building->items->first()->subtotal);

        $mdf = $daban->rates->first();
        $this->actingAs($this->accountant)
            ->post(route('vault.lines.staff-pay.store'), [
                'staff_id' => $daban->id,
                'occurred_on' => '2026-10-12',
                'site_kind' => VaultLine::SITE_VILLA,
                'villa_number' => '7',
                'zone' => 'North',
                'area' => '120m',
                'apply_insurance' => false,
                'items' => [[
                    'staff_rate_id' => $mdf->id,
                    'item_name' => $mdf->item_name,
                    'unit' => 'دانە',
                    'quantity' => 3,
                    'unit_rate' => 20000,
                    'currency' => DualCurrency::IQD,
                ]],
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $villa = VaultLine::query()->where('site_kind', VaultLine::SITE_VILLA)->latest('id')->first();
        $this->assertNotNull($villa);
        $this->assertSame(60000.0, (float) $villa->amount);
        $this->assertSame(0.0, (float) $villa->hold_amount);
        $this->assertSame('7', $villa->villa_number);
        $this->assertSame('North', $villa->zone);
        $this->assertSame('120m', $villa->area);
        $this->assertSame('دەرگای MDF', $villa->items->first()->item_name);
        $this->assertCount(0, $daban->fresh()->rates->where('item_name', 'دەرگای شافت'));
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
        $this->assertStringContainsString('expenses.create', $source);
        $this->assertStringNotContainsString('vault.lines.expense.create', $source);
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

    public function test_staff_pay_form_offers_cascading_place_suggestions(): void
    {
        $project = Project::query()->create([
            'name' => 'Block A',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $block = BuildingBlock::query()->create([
            'project_id' => $project->id,
            'code' => 'A1',
            'name' => 'Block A1',
        ]);
        ApartmentUnit::query()->create([
            'project_id' => $project->id,
            'building_block_id' => $block->id,
            'unit_label' => '12',
            'floor_number' => 3,
            'category' => ApartmentUnit::CATEGORY_MDF,
        ]);

        VaultLine::query()->create([
            'vault_id' => $this->vault->id,
            'kind' => VaultLine::KIND_UNIT_PAY,
            'occurred_on' => '2026-10-04',
            'amount' => 1000,
            'currency' => DualCurrency::IQD,
            'project_id' => $project->id,
            'site_kind' => VaultLine::SITE_VILLA,
            'villa_number' => '7',
            'zone' => 'North',
            'area' => '120m',
        ]);
        VaultLine::query()->create([
            'vault_id' => $this->vault->id,
            'kind' => VaultLine::KIND_UNIT_PAY,
            'occurred_on' => '2026-10-04',
            'amount' => 2000,
            'currency' => DualCurrency::IQD,
            'project_id' => $project->id,
            'site_kind' => VaultLine::SITE_BUILDING,
            'block' => 'A1',
            'zone' => 'Z2',
            'floor' => '3',
            'apartment_number' => '12',
            'apartment_model' => 'B',
        ]);

        $this->actingAs($this->accountant)
            ->get(route('vault.lines.staff-pay.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vault/Simple/StaffPayForm')
                ->where('placeSuggestions', function ($rows) use ($project) {
                    $list = collect($rows);

                    return $list->contains(fn ($row) => ($row['site_kind'] ?? null) === 'building'
                        && ($row['block'] ?? null) === 'A1'
                        && ($row['floor'] ?? null) === '3'
                        && ($row['apartment_number'] ?? null) === '12'
                        && (int) ($row['project_id'] ?? 0) === $project->id)
                        && $list->contains(fn ($row) => ($row['site_kind'] ?? null) === 'villa'
                            && ($row['villa_number'] ?? null) === '7'
                            && ($row['zone'] ?? null) === 'North'
                            && ($row['area'] ?? null) === '120m')
                        && $list->contains(fn ($row) => ($row['apartment_model'] ?? null) === 'B'
                            && ($row['zone'] ?? null) === 'Z2');
                })
            );

        $source = file_get_contents(resource_path('js/Pages/Vault/Simple/StaffPayForm.jsx'));
        $this->assertStringContainsString('placeSuggestions', $source);
        $this->assertStringContainsString('SuggestionCombobox', $source);
        $this->assertStringContainsString("setPlace('block'", $source);
        $this->assertStringContainsString("setPlace('villa_number'", $source);
    }

    public function test_staff_and_staff_pay_can_be_edited_and_deleted(): void
    {
        $staff = Staff::query()->create([
            'name' => 'Hunar',
            'phone' => '0750',
            'role' => 'دەرگاچیی',
            'pay_model' => Staff::PAY_UNIT,
            'kind' => Staff::KIND_UNIT,
            'currency' => DualCurrency::IQD,
        ]);
        $rate = StaffRate::query()->create([
            'staff_id' => $staff->id,
            'item_name' => 'دەرگای شافت',
            'unit' => 'دانە',
            'rate' => 8000,
            'currency' => DualCurrency::IQD,
        ]);
        $line = $this->service->postUnitPayWithItems([
            'staff_id' => $staff->id,
            'occurred_on' => '2026-10-06',
            'currency' => DualCurrency::IQD,
            'purpose' => 'Shaft',
            'vault_id' => $this->vault->id,
            'apply_insurance' => true,
            'items' => [[
                'staff_rate_id' => $rate->id,
                'item_name' => 'دەرگای شافت',
                'unit' => 'دانە',
                'quantity' => 1,
                'unit_rate' => 8000,
                'currency' => DualCurrency::IQD,
            ]],
        ]);

        $this->actingAs($this->stock)
            ->delete(route('staff.destroy', $staff))
            ->assertForbidden();
        $this->actingAs($this->stock)
            ->delete(route('vault.lines.staff-pay.destroy', $line))
            ->assertForbidden();

        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $this->actingAs($boss)
            ->get(route('vault.job-pay.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEditRows', true)
                ->where('canCreate', false)
            );
        $this->actingAs($boss)
            ->get(route('vault.lines.staff-pay.edit', $line))
            ->assertOk();

        $this->actingAs($this->accountant)
            ->get(route('vault.lines.staff-pay.edit', $line))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vault/Simple/StaffPayForm')
                ->where('line.id', $line->id)
                ->where('line.purpose', 'Shaft')
                ->where('line.items.0.quantity', 1)
            );

        $this->actingAs($this->accountant)
            ->put(route('vault.lines.staff-pay.update', $line), [
                'staff_id' => $staff->id,
                'occurred_on' => '2026-10-06',
                'purpose' => 'Shaft revised',
                'apply_insurance' => true,
                'site_kind' => VaultLine::SITE_BUILDING,
                'block' => 'A',
                'floor' => '2',
                'items' => [[
                    'staff_rate_id' => $rate->id,
                    'item_name' => 'دەرگای شافت',
                    'unit' => 'دانە',
                    'quantity' => 2,
                    'unit_rate' => 8000,
                    'currency' => DualCurrency::IQD,
                ]],
            ])
            ->assertRedirect(route('vault.job-pay.index'));

        $this->assertSoftDeleted('vault_lines', ['id' => $line->id]);
        $fresh = VaultLine::query()->where('purpose', 'Shaft revised')->first();
        $this->assertNotNull($fresh);
        $this->assertSame(16000.0, (float) $fresh->amount);
        $this->assertSame(1600.0, (float) $fresh->hold_amount);
        $this->assertSame('A', $fresh->block);
        $this->assertSame(2.0, (float) $fresh->items()->first()->quantity);

        $this->actingAs($this->accountant)
            ->from(route('vault.job-pay.index'))
            ->delete(route('vault.lines.staff-pay.destroy', $fresh))
            ->assertRedirect(route('vault.job-pay.index'));
        $this->assertSoftDeleted('vault_lines', ['id' => $fresh->id]);

        $this->actingAs($this->accountant)
            ->delete(route('staff.destroy', $staff))
            ->assertRedirect(route('staff.index'));
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);

        $index = file_get_contents(resource_path('js/Pages/Staff/Index.jsx'));
        $pays = file_get_contents(resource_path('js/Pages/Vault/Simple/JobPayIndex.jsx'));
        $this->assertStringContainsString("route('staff.destroy'", $index);
        $this->assertStringContainsString("route('staff.edit'", $index);
        // Actions column only — not duplicated under the name cell.
        $this->assertSame(2, substr_count($index, '<DeskRowActions'));
        $this->assertStringContainsString("route('vault.lines.staff-pay.destroy'", $pays);
        $this->assertStringContainsString("route('vault.lines.staff-pay.edit'", $pays);
    }
}
