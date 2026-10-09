<?php

namespace Tests\Feature;

use App\Models\Penalty;
use App\Models\Project;
use App\Models\Staff;
use App\Support\DualCurrency;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PenaltiesIndexPhase24Test extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_sees_dual_totals_and_status_type_filters(): void
    {
        $project = Project::query()->create([
            'name' => 'Penalty Index QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $worker = Staff::query()->create([
            'name' => 'Penalty Worker',
            'kind' => Staff::KIND_SALARY,
            'trade' => 'laborer',
            'monthly_salary' => 600,
            'currency' => DualCurrency::USD,
        ]);

        Penalty::query()->create([
            'staff_id' => $worker->id,
            'project_id' => $project->id,
            'type' => Penalty::TYPE_LATE,
            'reason' => 'Late arrival',
            'amount_usd' => 25,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'occurred_on' => '2026-09-10',
            'status' => Penalty::STATUS_PENDING,
        ]);

        Penalty::query()->create([
            'staff_id' => $worker->id,
            'project_id' => $project->id,
            'type' => Penalty::TYPE_SAFETY,
            'reason' => 'Helmet',
            'amount_usd' => 0,
            'amount_iqd' => 50000,
            'currency' => 'IQD',
            'occurred_on' => '2026-09-12',
            'status' => Penalty::STATUS_APPLIED,
        ]);

        Penalty::query()->create([
            'staff_id' => $worker->id,
            'project_id' => $project->id,
            'type' => Penalty::TYPE_DAMAGE,
            'reason' => 'Waived damage',
            'amount_usd' => 10,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'occurred_on' => '2026-09-14',
            'status' => Penalty::STATUS_WAIVED,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('penalties.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Penalties/Index')
                ->has('penalties', 3)
                ->where('overview.count', 3)
                ->where('overview.pending', 1)
                ->where('overview.applied', 1)
                ->where('overview.waived', 1)
                ->where('overview.amount_usd', 25)
                ->where('overview.amount_iqd', 50000)
                ->where('overview.pending_usd', 25)
                ->where('overview.pending_iqd', 0)
                ->where('statusCounts.all', 3)
                ->where('statusCounts.pending', 1)
                ->where('statusCounts.applied', 1)
                ->where('statusCounts.waived', 1)
                ->has('filters')
                ->has('types')
                ->has('statuses')
            );

        $this->get(route('penalties.index', ['status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Penalties/Index')
                ->has('penalties', 1)
                ->where('filters.status', 'pending')
                ->where('overview.count', 1)
                ->where('overview.pending', 1)
                ->where('overview.amount_usd', 25)
                ->where('penalties.0.type', Penalty::TYPE_LATE)
            );

        $this->get(route('penalties.index', ['type' => 'safety']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('penalties', 1)
                ->where('filters.type', 'safety')
                ->where('overview.applied', 1)
                ->where('overview.amount_iqd', 50000)
                ->where('penalties.0.type', Penalty::TYPE_SAFETY)
            );
    }

    public function test_empty_penalties_returns_zero_overview(): void
    {
        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('penalties.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Penalties/Index')
                ->has('penalties', 0)
                ->where('overview.count', 0)
                ->where('overview.amount_usd', 0)
                ->where('overview.amount_iqd', 0)
            );

        $source = file_get_contents(resource_path('js/Pages/Penalties/Index.jsx'));
        $this->assertStringNotContainsString(
            'dashboards.payroll',
            $source,
            'Penalties index must not call removed payroll route (Ziggy crash)',
        );
        $this->assertStringContainsString('vault.lines.salary.create', $source);
    }
}
