<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_dashboard_monthly_summary_per_worker(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create(['name' => 'Payroll Site']);

        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Pay Dash Worker',
            'daily_rate_usd' => 50,
            'overtime_rate_usd' => 75,
        ]);

        Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => '2026-09-01',
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 0,
            'overtime_hours' => 2,
        ]);
        Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => '2026-09-02',
            'status' => Attendance::STATUS_LATE,
            'late_minutes' => 20,
            'overtime_hours' => 0,
        ]);
        Attendance::query()->create([
            'worker_id' => $worker->id,
            'date' => '2026-09-03',
            'status' => Attendance::STATUS_ABSENT_UNEXCUSED,
            'late_minutes' => 0,
            'overtime_hours' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('dashboards.payroll', [
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboards/Payroll')
            ->where('month', '2026-09')
            ->where('from', '2026-09-01')
            ->where('to', '2026-09-30')
            ->has('rows', 1)
            ->where('rows.0.worker_id', $worker->id)
            ->where('rows.0.days_present', 2)
            ->where('rows.0.overtime_hours', fn ($v) => (float) $v === 2.0)
            ->where('rows.0.penalties_usd', fn ($v) => (float) $v === 55.0) // 20*0.25 + 50 absence
            ->where('rows.0.net_pay_usd', fn ($v) => (float) $v === 195.0) // 100 base + 150 OT - 55
            ->where('totals.net_pay_usd', fn ($v) => (float) $v === 195.0));
    }

    public function test_payroll_dashboard_filters_by_project(): void
    {
        $user = User::factory()->create();
        $a = Project::query()->create(['name' => 'Site A']);
        $b = Project::query()->create(['name' => 'Site B']);

        Worker::query()->create(['project_id' => $a->id, 'name' => 'A Worker']);
        Worker::query()->create(['project_id' => $b->id, 'name' => 'B Worker']);

        $response = $this->actingAs($user)->get(route('dashboards.payroll', [
            'month' => '2026-09',
            'project_id' => $a->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboards/Payroll')
            ->has('rows', 1)
            ->where('rows.0.name', 'A Worker')
            ->where('projectId', $a->id));
    }
}
