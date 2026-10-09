<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Project;
use App\Models\Vault;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpenseCreatePhase21Test extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_open_expense_create_with_available_cash(): void
    {
        Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 1000,
            'balance_iqd' => 5_000_000,
        ]);

        Project::query()->create([
            'name' => 'Create Form Site',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->get(route('expenses.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Create')
                ->has('projects')
                ->has('categories')
                ->has('paymentMethods')
                ->has('currencies')
                ->has('availableCash')
            );
    }

    public function test_accountant_can_store_pending_expense_from_receipt_form(): void
    {
        $vault = Vault::query()->create([
            'name' => VaultSeeder::NAME,
            'balance_usd' => 0,
            'balance_iqd' => 10_000_000,
        ]);

        $project = Project::query()->create([
            'name' => 'Receipt Site',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->actingAsRole(Roles::ACCOUNTANT);

        $this->post(route('expenses.store'), [
            'project_id' => $project->id,
            'category' => Expense::CATEGORY_FUEL,
            'currency' => 'IQD',
            'amount' => 250000,
            'expense_date' => '2026-09-20',
            'supplier' => 'Erbil Fuel',
            'payment_method' => Expense::PAYMENT_CASH,
            'description' => 'Generator diesel',
            'vault_id' => $vault->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'project_id' => $project->id,
            'category' => Expense::CATEGORY_FUEL,
            'currency' => 'IQD',
            'amount_iqd' => 250000,
            'supplier' => 'Erbil Fuel',
            'approval_status' => Expense::STATUS_PENDING,
        ]);
    }
}
