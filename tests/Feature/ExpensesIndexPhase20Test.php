<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Project;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpensesIndexPhase20Test extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_sees_dual_spend_totals_and_status_filters(): void
    {
        $project = Project::query()->create([
            'name' => 'Expense Index QA',
            'status' => Project::STATUS_ACTIVE,
        ]);

        Expense::query()->create([
            'project_id' => $project->id,
            'category' => Expense::CATEGORY_MATERIALS,
            'amount_usd' => 120,
            'amount_iqd' => 0,
            'currency' => 'USD',
            'expense_date' => '2026-09-10',
            'supplier' => 'USD Supply',
            'approval_status' => Expense::STATUS_PENDING,
            'payment_method' => Expense::PAYMENT_CASH,
        ]);

        Expense::query()->create([
            'project_id' => $project->id,
            'category' => Expense::CATEGORY_FUEL,
            'amount_usd' => 0,
            'amount_iqd' => 500000,
            'currency' => 'IQD',
            'expense_date' => '2026-09-12',
            'supplier' => 'Fuel Co',
            'approval_status' => Expense::STATUS_APPROVED,
            'payment_method' => Expense::PAYMENT_CASH,
        ]);

        Expense::query()->create([
            'project_id' => $project->id,
            'category' => Expense::CATEGORY_FOOD,
            'amount_usd' => 0,
            'amount_iqd' => 80000,
            'currency' => 'IQD',
            'expense_date' => '2026-09-14',
            'supplier' => 'Meals',
            'approval_status' => Expense::STATUS_HELD,
            'payment_method' => Expense::PAYMENT_CASH,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->has('expenses', 3)
                ->where('spendTotals.amount_usd', 120)
                ->where('spendTotals.amount_iqd', 580000)
                ->where('spendTotals.count', 3)
                ->where('statusCounts.all', 3)
                ->where('statusCounts.pending', 1)
                ->where('statusCounts.approved', 1)
                ->where('statusCounts.held', 1)
                ->has('filters')
                ->has('categories')
                ->has('statuses')
            );

        $this->get(route('expenses.index', ['status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->has('expenses', 1)
                ->where('filters.status', 'pending')
                ->where('spendTotals.count', 1)
                ->where('spendTotals.amount_usd', 120)
            );

        $this->get(route('expenses.index', ['category' => 'fuel']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->has('expenses', 1)
                ->where('filters.category', 'fuel')
                ->where('expenses.0.category', 'fuel')
                ->where('spendTotals.amount_iqd', 500000)
            );
    }

    public function test_empty_expenses_index_still_returns_overview_props(): void
    {
        $this->actingAsRole(Roles::SUPER_ADMIN);

        $this->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->has('expenses', 0)
                ->where('spendTotals.count', 0)
                ->where('statusCounts.all', 0)
            );
    }
}
