<?php

namespace Tests\Feature;

use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonClassifyPhase27Test extends TestCase
{
    use RefreshDatabase;

    public function test_unclassified_index_filter_and_show_exposes_classify(): void
    {
        $staff = Worker::query()->create([
            'name' => 'Staff Person',
            'labor_kind' => Worker::LABOR_KIND_STAFF,
            'role' => Worker::ROLE_SUBCONTRACTOR,
            'unit_rate' => 10,
            'rate_unit' => 'm2',
            'rate_currency' => 'USD',
        ]);

        $unclassified = Worker::query()->create([
            'name' => 'Needs Path',
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
            'role' => Worker::ROLE_LABORER,
        ]);

        $this->actingAsRole(Roles::BOSS_CONTRACTOR);

        $this->get(route('workers.index', ['labor_kind' => 'unclassified']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Index')
                ->where('filters.labor_kind', 'unclassified')
                ->has('workers', 1)
                ->where('workers.0.id', $unclassified->id)
                ->where('kindCounts.unclassified', 1)
                ->where('kindCounts.staff', 1)
            );

        $this->get(route('workers.show', $unclassified))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('worker.id', $unclassified->id)
                ->where('worker.labor_kind', Worker::LABOR_KIND_UNCLASSIFIED)
                ->where('canClassify', true)
            );

        $this->get(route('workers.show', $staff))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('worker.id', $staff->id)
                ->where('canClassify', false)
            );
    }

    public function test_classify_staff_then_hides_form_and_worker_path(): void
    {
        $person = Worker::query()->create([
            'name' => 'Classify Me',
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
        ]);

        $boss = $this->actingAsRole(Roles::BOSS_CONTRACTOR);

        $this->actingAs($boss)
            ->post(route('workers.classify', $person), [
                'labor_kind' => 'staff',
                'rate_unit' => 'villa',
                'rate_currency' => 'IQD',
                'unit_rate' => 250000,
            ])
            ->assertRedirect();

        $person->refresh();
        $this->assertTrue($person->isStaff());
        $this->assertSame('villa', $person->rate_unit);
        $this->assertSame('IQD', $person->rate_currency);
        $this->assertEqualsWithDelta(250000.0, (float) $person->unit_rate, 0.01);

        $this->get(route('workers.show', $person))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canClassify', false)
                ->where('worker.labor_kind', Worker::LABOR_KIND_STAFF)
            );

        $salaryPerson = Worker::query()->create([
            'name' => 'Salary Path',
            'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED,
        ]);

        $this->actingAs($boss)
            ->post(route('workers.classify', $salaryPerson), [
                'labor_kind' => 'worker',
                'monthly_salary_usd' => 750,
                'monthly_salary_iqd' => 1_000_000,
            ])
            ->assertRedirect();

        $salaryPerson->refresh();
        $this->assertTrue($salaryPerson->isEmployee());
        $this->assertEqualsWithDelta(750.0, (float) $salaryPerson->monthly_salary_usd, 0.01);
        $this->assertEqualsWithDelta(1_000_000.0, (float) $salaryPerson->monthly_salary_iqd, 0.01);

        $stock = $this->userWithRole(Roles::STOCK_MANAGER);
        $this->actingAs($stock)
            ->post(route('workers.classify', $salaryPerson), [
                'labor_kind' => 'staff',
            ])
            ->assertForbidden();
    }
}
