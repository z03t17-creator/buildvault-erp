<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClientAdvance;
use App\Models\ClientRetentionHold;
use App\Models\EmployeeAdvance;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\StaffStatement;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\AttendanceService;
use App\Services\ClientAdvanceService;
use App\Services\ClientRetentionHoldService;
use App\Services\StaffSettlementService;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceRetentionPhase24Test extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Vault $vault;

    private Worker $employee;

    private Worker $staff;

    protected function setUp(): void
    {
        parent::setUp();
        if (! \Illuminate\Support\Facades\Route::has('workers.index')) {
            $this->markTestSkipped('Module surface dropped in staff rewire slice.');
        }


        $this->vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 5000,
            'balance_iqd' => 1_000_000,
        ]);

        $this->project = Project::query()->create([
            'name' => 'Mayorca Attendance QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->employee = Worker::query()->create([
            'name' => 'Demo Employee',
            'project_id' => $this->project->id,
            'labor_kind' => Worker::LABOR_KIND_WORKER,
            'role' => Worker::ROLE_LABORER,
            'monthly_salary_usd' => 600,
            'monthly_salary_iqd' => 0,
            'daily_rate_usd' => 20,
        ]);

        $this->staff = Worker::query()->create([
            'name' => 'Demo Staff',
            'project_id' => $this->project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'rate_unit' => 'item',
            'rate_currency' => 'USD',
            'unit_rate' => 10,
        ]);
    }

    public function test_stock_manager_can_view_and_check_in_employees(): void
    {
        $stock = $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Matrix')
                ->has('grid')
            );

        $this->post(route('attendance.check-in'), [
            'date' => now()->toDateString(),
            'check_in' => '08:05',
            'shift_start' => '08:00',
            'worker_ids' => [$this->employee->id],
            'project_id' => $this->project->id,
        ])->assertRedirect();

        $row = Attendance::query()->where('worker_id', $this->employee->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(Attendance::STATUS_LATE, $row->status);
        $this->assertSame(5, (int) $row->late_minutes);
        $this->assertFalse($row->forfeit_day);
        $this->assertSame($stock->id, $row->entered_by);
    }

    public function test_late_over_30_minutes_creates_forfeit_day_penalty(): void
    {
        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->post(route('attendance.check-in'), [
            'date' => now()->toDateString(),
            'check_in' => '08:45',
            'shift_start' => '08:00',
            'worker_ids' => [$this->employee->id],
            'project_id' => $this->project->id,
        ])->assertRedirect();

        $row = Attendance::query()->where('worker_id', $this->employee->id)->first();
        $this->assertTrue($row->forfeit_day);
        $this->assertGreaterThan(30, (int) $row->late_minutes);
        $this->assertNotNull($row->penalty_id);

        $penalty = Penalty::query()->find($row->penalty_id);
        $this->assertSame(Penalty::TYPE_FORFEIT_DAY, $penalty->type);
        $this->assertSame(Penalty::STATUS_APPLIED, $penalty->status);
        // monthly 600 / 30 = 20 USD day
        $this->assertSame('20.00', (string) $penalty->amount_usd);
        $this->assertSame('0.00', (string) $penalty->amount_iqd);
    }

    public function test_staff_cannot_be_checked_in_via_attendance(): void
    {
        $this->actingAsRole(Roles::STOCK_MANAGER);

        $this->post(route('attendance.check-in'), [
            'date' => now()->toDateString(),
            'check_in' => '09:00',
            'worker_ids' => [$this->staff->id],
            'project_id' => $this->project->id,
        ])->assertRedirect()
            ->assertSessionHasErrors('check_in');

        $this->assertSame(0, Attendance::query()->where('worker_id', $this->staff->id)->count());
    }

    public function test_accountant_can_view_attendance_but_not_manage(): void
    {
        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('attendance.index'))->assertOk();

        $this->post(route('attendance.check-in'), [
            'date' => now()->toDateString(),
            'check_in' => '08:00',
            'worker_ids' => [$this->employee->id],
        ])->assertForbidden();
    }

    public function test_boss_can_view_attendance_but_not_manage(): void
    {
        $this->actingAsRole(Roles::BOSS_CONTRACTOR);

        $this->get(route('attendance.index'))->assertOk();
        $this->post(route('attendance.check-in'), [
            'date' => now()->toDateString(),
            'check_in' => '08:00',
            'worker_ids' => [$this->employee->id],
        ])->assertForbidden();
    }

    public function test_staff_settlement_gross_minus_retention_advances_penalties(): void
    {
        EmployeeAdvance::query()->create([
            'worker_id' => $this->staff->id,
            'project_id' => $this->project->id,
            'amount_usd' => 50,
            'remaining_usd' => 50,
            'amount_iqd' => 0,
            'remaining_iqd' => 0,
            'currency' => 'USD',
            'advanced_on' => now()->toDateString(),
            'status' => EmployeeAdvance::STATUS_OPEN,
            'reason' => 'Advance 1',
        ]);
        EmployeeAdvance::query()->create([
            'worker_id' => $this->staff->id,
            'project_id' => $this->project->id,
            'amount_usd' => 25,
            'remaining_usd' => 25,
            'amount_iqd' => 0,
            'remaining_iqd' => 0,
            'currency' => 'USD',
            'advanced_on' => now()->toDateString(),
            'status' => EmployeeAdvance::STATUS_OPEN,
            'reason' => 'Advance 2',
        ]);
        Penalty::query()->create([
            'worker_id' => $this->staff->id,
            'project_id' => $this->project->id,
            'type' => Penalty::TYPE_OTHER,
            'reason' => 'Fine',
            'amount_usd' => 10,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'status' => Penalty::STATUS_APPLIED,
            'occurred_on' => now()->toDateString(),
        ]);

        StaffStatement::query()->create([
            'worker_id' => $this->staff->id,
            'project_id' => $this->project->id,
            'earned_usd' => 1000,
            'earned_iqd' => 0,
            'status' => StaffStatement::STATUS_OPEN,
        ]);

        $preview = app(StaffSettlementService::class)->preview($this->staff);
        // retention 10% of 1000 = 100; advances 75; penalties 10; net = 1000-100-75-10 = 815
        $this->assertSame(1000.0, $preview['gross_usd']);
        $this->assertSame(100.0, $preview['retention_usd']);
        $this->assertSame(75.0, $preview['advances_usd']);
        $this->assertSame(10.0, $preview['penalties_usd']);
        $this->assertSame(815.0, $preview['net_usd']);
        $this->assertFalse($preview['employee_salary_has_retention']);
    }

    public function test_employee_settlement_has_no_automatic_retention(): void
    {
        $preview = app(StaffSettlementService::class)->preview($this->employee);
        $this->assertSame(0.0, $preview['retention_usd']);
        $this->assertSame(0.0, $preview['hold_pct']);
        $this->assertFalse($preview['employee_salary_has_retention']);
    }

    public function test_client_retention_matures_and_releases(): void
    {
        $accountant = $this->actingAsRole(Roles::ACCOUNTANT);

        $advance = app(ClientAdvanceService::class)->create([
            'project_id' => $this->project->id,
            'client_name' => 'Zhako Client',
            'currency' => 'USD',
            'amount' => 1000,
            'received_on' => now()->subDays(200)->toDateString(),
            'lock_retention' => true,
        ], $accountant);

        $hold = ClientRetentionHold::query()->where('client_advance_id', $advance->id)->first();
        $this->assertNotNull($hold);
        $this->assertSame(ClientRetentionHold::STATUS_HOLDING, $hold->status);
        $this->assertSame('100.00', (string) $hold->amount_usd);

        // Backdate maturity
        $hold->maturity_date = now()->subDay()->toDateString();
        $hold->save();

        $service = app(ClientRetentionHoldService::class);
        $service->markDueAsMatured();
        $hold->refresh();
        $this->assertSame(ClientRetentionHold::STATUS_MATURED, $hold->status);

        $this->post(route('retention-holds.client-release', $hold))->assertRedirect();
        $hold->refresh();
        $this->assertSame(ClientRetentionHold::STATUS_RELEASED, $hold->status);
        $this->assertSame('100.00', (string) $hold->released_amount_usd);
    }

    public function test_attendance_service_late_math_unit(): void
    {
        $svc = app(AttendanceService::class);
        $this->assertSame(0, $svc->computeLateMinutes('08:00', now(), '08:00'));
        $this->assertSame(31, $svc->computeLateMinutes('08:31', now(), '08:00'));
    }
}
