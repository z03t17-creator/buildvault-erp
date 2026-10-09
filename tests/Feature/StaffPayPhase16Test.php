<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffPayPhase16Test extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_sees_staff_pay_hub_with_dual_totals(): void
    {
        $project = Project::query()->create([
            'name' => 'Staff Pay QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $staff = Worker::query()->create([
            'name' => 'Pay Staff',
            'project_id' => $project->id,
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
        ]);

        EmployeeAdvance::query()->create([
            'worker_id' => $staff->id,
            'project_id' => $project->id,
            'amount_usd' => 100,
            'remaining_usd' => 40,
            'amount_iqd' => 0,
            'remaining_iqd' => 0,
            'advanced_on' => now()->toDateString(),
            'reason' => 'Unit tools',
            'repayment_method' => EmployeeAdvance::REPAY_CASH,
            'status' => EmployeeAdvance::STATUS_OPEN,
            'entered_by' => null,
        ]);

        EmployeeAdvance::query()->create([
            'worker_id' => $staff->id,
            'project_id' => $project->id,
            'amount_usd' => 0,
            'remaining_usd' => 0,
            'amount_iqd' => 250000,
            'remaining_iqd' => 250000,
            'advanced_on' => now()->toDateString(),
            'reason' => 'Cash help',
            'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
            'status' => EmployeeAdvance::STATUS_OPEN,
            'entered_by' => null,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('advances.index'))
            ->assertRedirect('/vault/lines/job-pay');
    }

    public function test_create_form_lists_people_with_labor_kind(): void
    {
        $this->markTestSkipped('Legacy /advances/create UI removed; company→staff pay is vault job-pay.');
    }
}
