<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Models\Vault;
use App\Services\ExpenseService;
use App\Services\VaultService;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Demo project expenses on Zhako Demo Tower (idempotent by supplier markers).
 */
class DemoExpensesSeeder extends Seeder
{
    public const MARKERS = [
        'DEMO-EXP-MATERIALS',
        'DEMO-EXP-FUEL',
        'DEMO-EXP-FOOD',
    ];

    public function run(): void
    {
        // Expect VaultSeeder / DemoHierarchySeeder / DemoUsersSeeder already run.
        $project = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first();
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        if (! $project || ! $vault) {
            return;
        }

        $existing = Expense::query()
            ->where('project_id', $project->id)
            ->whereIn('supplier', self::MARKERS)
            ->count();

        if ($existing >= count(self::MARKERS)) {
            return;
        }

        // Ensure expenses pool can fund demo approvals.
        if ((float) $vault->balance_usd < 3000) {
            app(VaultService::class)->deposit($project, 10000, $vault);
            $vault->refresh();
        }

        $accountant = User::query()
            ->role(Roles::ACCOUNTANT)
            ->first()
            ?? User::query()->role(Roles::SUPER_ADMIN)->first();

        $service = app(ExpenseService::class);

        $demos = [
            [
                'supplier' => 'DEMO-EXP-MATERIALS',
                'category' => Expense::CATEGORY_MATERIALS,
                'amount_iqd' => 2_620_000,
                'payment_method' => Expense::PAYMENT_BANK_TRANSFER,
                'description' => 'Demo cement & rebar delivery',
                'expense_date' => now()->subDays(12)->toDateString(),
                'approve' => true,
            ],
            [
                'supplier' => 'DEMO-EXP-FUEL',
                'category' => Expense::CATEGORY_FUEL,
                'amount_iqd' => 655_000,
                'payment_method' => Expense::PAYMENT_CASH,
                'description' => 'Demo generator diesel',
                'expense_date' => now()->subDays(5)->toDateString(),
                'approve' => true,
            ],
            [
                'supplier' => 'DEMO-EXP-FOOD',
                'category' => Expense::CATEGORY_FOOD,
                'amount_iqd' => 262_000,
                'payment_method' => Expense::PAYMENT_CASH,
                'description' => 'Demo site crew meals (pending approval)',
                'expense_date' => now()->subDay()->toDateString(),
                'approve' => false,
            ],
        ];

        foreach ($demos as $row) {
            if (Expense::query()->where('supplier', $row['supplier'])->exists()) {
                continue;
            }

            $expense = $service->create([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'category' => $row['category'],
                'amount_iqd' => $row['amount_iqd'],
                'expense_date' => $row['expense_date'],
                'supplier' => $row['supplier'],
                'payment_method' => $row['payment_method'],
                'description' => $row['description'],
                'created_by' => $accountant?->id,
            ]);

            if ($row['approve']) {
                $service->approve($expense, $accountant);
            }
        }
    }
}
