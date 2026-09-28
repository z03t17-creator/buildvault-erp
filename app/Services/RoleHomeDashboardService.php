<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\RetentionHold;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Support\AuditActions;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Phase 14 — role-specific home dashboard payloads (real DB numbers, IQD).
 *
 * Each role only receives its slice; callers must gate by Spatie role.
 */
class RoleHomeDashboardService
{
    public function __construct(
        private readonly ExchangeRateService $fx,
        private readonly LiquidityService $liquidity,
        private readonly StockService $stock,
        private readonly VaultLedgerService $ledger,
        private readonly MonthlySettlementService $settlements,
        private readonly ProjectFinancialService $projectFinancials,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summaryForRole(string $role): array
    {
        return match ($role) {
            Roles::SUPER_ADMIN => $this->superAdmin(),
            Roles::BOSS_CONTRACTOR => $this->boss(),
            Roles::ACCOUNTANT => $this->accountant(),
            Roles::STOCK_MANAGER => $this->stockManager(),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function superAdmin(): array
    {
        $active = User::query()->where('status', User::STATUS_ACTIVE)->count();
        $disabled = User::query()->where('status', User::STATUS_DISABLED)->count();
        // Legacy rows without status count as active.
        $legacyActive = User::query()->whereNull('status')->count();

        $lastLogins = User::query()
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'last_login_at', 'status'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status ?? User::STATUS_ACTIVE,
                'last_login_at' => optional($u->last_login_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentActivity = [];
        if (Schema::hasTable('activity_log')) {
            $recentActivity = Activity::query()
                ->where('log_name', AuditActions::LOG_NAME)
                ->with('causer:id,name')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (Activity $a) => [
                    'id' => $a->id,
                    'event' => $a->event ?? $a->description,
                    'description' => $a->description,
                    'created_at' => optional($a->created_at)?->toIso8601String(),
                    'causer_name' => $a->causer?->name,
                ])
                ->values()
                ->all();
        }

        $backup = null;
        if (Schema::hasTable('backups')) {
            $row = Backup::query()->orderByDesc('id')->first();
            if ($row) {
                $backup = [
                    'id' => $row->id,
                    'type' => $row->type,
                    'status' => $row->status,
                    'filename' => $row->filename,
                    'finished_at' => optional($row->finished_at)?->toIso8601String(),
                    'started_at' => optional($row->started_at)?->toIso8601String(),
                    'message' => $row->message,
                ];
            }
        }

        $vault = $this->zhakoVault();
        $ledgerOk = true;
        if ($vault) {
            $balances = $this->ledger->balances($vault);
            $ledgerOk = (bool) $balances['balance_matches_ledger'];
        }

        return [
            'users' => User::query()->count(),
            'users_active' => $active + $legacyActive,
            'users_disabled' => $disabled,
            'last_logins' => $lastLogins,
            'recent_activity' => $recentActivity,
            'backup' => $backup,
            'audit_events' => $this->auditEventCount(),
            'pending_payouts' => Payout::query()->where('status', Payout::STATUS_PENDING)->count(),
            'matured_holds' => RetentionHold::query()->where('status', RetentionHold::STATUS_MATURED)->count(),
            'projects' => Project::query()->count(),
            'health' => [
                'ledger_ok' => $ledgerOk,
                'pending_payouts' => Payout::query()->where('status', Payout::STATUS_PENDING)->count(),
                'matured_holds' => RetentionHold::query()->where('status', RetentionHold::STATUS_MATURED)->count(),
                'has_backup' => $backup !== null,
                'last_backup_ok' => ($backup['status'] ?? null) === Backup::STATUS_COMPLETED,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boss(): array
    {
        $vault = $this->zhakoVault();
        $month = now()->format('Y-m');
        $settlement = $vault
            ? $this->settlements->preview($month, null, $vault)
            : null;

        $balances = $vault ? $this->ledger->balances($vault) : null;

        $moneyReceived = $settlement['money_received_iqd'] ?? $this->sumTypes(
            Transaction::MONEY_RECEIVED_TYPES,
            $vault?->id,
        );
        $payroll = $settlement['payroll_iqd'] ?? $this->sumTypes([Transaction::TYPE_PAYROLL], $vault?->id);
        $advances = $settlement['employee_advances_iqd'] ?? $this->sumTypes([Transaction::TYPE_ADVANCE], $vault?->id);
        $insurance = $settlement['reserved_insurance_iqd']
            ?? ($balances['reserved_iqd'] ?? 0.0);
        $projectExpenses = $settlement['project_expenses_iqd'] ?? $this->sumTypes([Transaction::TYPE_EXPENSE], $vault?->id);
        $otherExpenses = $settlement['other_expenses_iqd'] ?? 0.0;
        $materialSpend = $this->totalMaterialSpendIqd();

        $spent = $settlement
            ? (float) $settlement['month_outflows_iqd']
            : round($projectExpenses + $payroll + $advances + $otherExpenses, 2);

        $projectCards = Project::query()
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(function (Project $project) {
                $fin = $this->projectFinancials->summary($project);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'money_received_iqd' => $fin['money_received_iqd'],
                    'material_cost_iqd' => $fin['material_cost_iqd'],
                    'payroll_cost_iqd' => $fin['payroll_cost_iqd'],
                    'project_expenses_iqd' => $fin['project_expenses_iqd'],
                    'net_position_iqd' => $fin['net_position_iqd'],
                    'contract_value_iqd' => $fin['contract_value_iqd'],
                ];
            })
            ->values()
            ->all();

        return [
            'year_month' => $month,
            'money_received_iqd' => round((float) $moneyReceived, 2),
            'money_spent_iqd' => round((float) $spent, 2),
            'vault_balance_iqd' => $balances['current_iqd'] ?? null,
            'available_iqd' => $balances['available_iqd'] ?? null,
            'reserved_insurance_iqd' => round((float) $insurance, 2),
            'pending_payouts_iqd' => $balances['pending_iqd'] ?? null,
            'payroll_totals_iqd' => round((float) $payroll, 2),
            'advances_iqd' => round((float) $advances, 2),
            'stock_material_spend_iqd' => round((float) $materialSpend, 2),
            'project_expenses_iqd' => round((float) $projectExpenses, 2),
            'projects' => count($projectCards),
            'project_cards' => $projectCards,
            'workers' => \App\Models\Worker::query()->count(),
            'pending_payouts_count' => Payout::query()->where('status', Payout::STATUS_PENDING)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountant(): array
    {
        $vault = $this->zhakoVault();
        $month = now()->format('Y-m');
        $settlement = $vault
            ? $this->settlements->preview($month, null, $vault)
            : null;

        $payrollDue = Payout::query()
            ->where('status', Payout::STATUS_PENDING)
            ->where('category', Payout::CATEGORY_PAYROLL);
        $pendingPayouts = Payout::query()->where('status', Payout::STATUS_PENDING);
        $pendingExpenses = Expense::query()->where('approval_status', Expense::STATUS_PENDING);
        $openAdvances = EmployeeAdvance::query()->where('status', EmployeeAdvance::STATUS_OPEN);
        $pendingPenalties = Penalty::query()->where('status', Penalty::STATUS_PENDING);
        $maturedHolds = RetentionHold::query()->where('status', RetentionHold::STATUS_MATURED);
        $holdingInsurance = RetentionHold::query()->where('status', RetentionHold::STATUS_HOLDING);

        $recentTx = [];
        if ($vault) {
            $recentTx = Transaction::query()
                ->forVault($vault->id)
                ->with(['project:id,name', 'creator:id,name'])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount_iqd' => round((float) $t->amount_iqd, 2),
                    'occurred_on' => optional($t->occurred_on)?->toDateString(),
                    'description' => $t->description,
                    'project_name' => $t->project?->name,
                    'creator_name' => $t->creator?->name,
                ])
                ->values()
                ->all();
        }

        return [
            'year_month' => $month,
            'payroll_due_count' => (clone $payrollDue)->count(),
            'payroll_due_iqd' => round((float) (clone $payrollDue)->sum('amount_iqd'), 2),
            'pending_calculations' => (clone $pendingPayouts)->count() + (clone $pendingExpenses)->count(),
            'pending_payouts' => (clone $pendingPayouts)->count(),
            'pending_expenses_count' => (clone $pendingExpenses)->count(),
            'pending_expenses_iqd' => round((float) (clone $pendingExpenses)->sum('amount_iqd'), 2),
            'advances_open_count' => (clone $openAdvances)->count(),
            'advances_open_iqd' => round((float) (clone $openAdvances)->sum('amount_iqd'), 2),
            'penalties_pending_count' => (clone $pendingPenalties)->count(),
            'penalties_pending_iqd' => round((float) (clone $pendingPenalties)->sum('amount_iqd'), 2),
            'insurance_matured_count' => (clone $maturedHolds)->count(),
            'insurance_holding_count' => (clone $holdingInsurance)->count(),
            'insurance_held_iqd' => $settlement['reserved_insurance_iqd']
                ?? ($vault ? round($this->liquidity->reservedInsuranceUsd($vault) * $this->fx->getUsdToIqd(), 0) : 0.0),
            'money_received_iqd' => $settlement['money_received_iqd'] ?? 0.0,
            'expenses_month_iqd' => $settlement['project_expenses_iqd'] ?? 0.0,
            'available_payment_iqd' => $settlement['available_money_for_payment_iqd'] ?? 0.0,
            'matured_holds' => (clone $maturedHolds)->count(),
            'projects' => Project::query()->count(),
            'workers' => \App\Models\Worker::query()->count(),
            'recent_transactions' => $recentTx,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockManager(): array
    {
        $summary = $this->stock->dashboardSummary();

        $byCategory = StockItem::query()
            ->get(['id', 'category', 'quantity', 'purchase_price_iqd'])
            ->groupBy(function (StockItem $item) {
                $cat = trim((string) ($item->category ?? ''));

                return $cat !== '' ? $cat : 'uncategorized';
            })
            ->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'items_count' => $items->count(),
                    'quantity_total' => round((float) $items->sum(fn (StockItem $i) => (float) $i->quantity), 3),
                    'value_iqd' => round((float) $items->sum(fn (StockItem $i) => $i->stockValueIqd()), 2),
                ];
            })
            ->sortBy('category')
            ->values()
            ->all();

        $recent = $summary['recent_movements']->map(fn ($m) => [
            'id' => $m->id,
            'type' => $m->type,
            'quantity' => round((float) $m->quantity, 3),
            'moved_on' => optional($m->moved_on)?->toDateString(),
            'previous_qty' => round((float) $m->previous_qty, 3),
            'new_qty' => round((float) $m->new_qty, 3),
            'item' => $m->item ? [
                'id' => $m->item->id,
                'name' => $m->item->name,
                'sku' => $m->item->sku,
                'unit' => $m->item->unit,
            ] : null,
            'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null,
            'project' => $m->project ? ['id' => $m->project->id, 'name' => $m->project->name] : null,
        ])->values()->all();

        return [
            'total_items' => $summary['total_items'],
            'stock_value_iqd' => $summary['stock_value_iqd'],
            'low_stock' => $summary['low_stock'],
            'out_of_stock' => $summary['out_of_stock'],
            'today_in_qty' => $summary['today_in_qty'],
            'today_out_qty' => $summary['today_out_qty'],
            'today_in_count' => $summary['today_in_count'],
            'today_out_count' => $summary['today_out_count'],
            'by_category' => $byCategory,
            'recent_movements' => $recent,
        ];
    }

    private function totalMaterialSpendIqd(): float
    {
        return round((float) \App\Models\StockMovement::query()
            ->where('type', \App\Models\StockMovement::TYPE_OUT)
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(purchase_price_iqd, 0)), 0) as total')
            ->value('total'), 2);
    }

    /**
     * @param  list<string>  $types
     */
    private function sumTypes(array $types, ?int $vaultId): float
    {
        if (! $vaultId) {
            return 0.0;
        }

        return round((float) Transaction::query()
            ->forVault($vaultId)
            ->whereIn('type', $types)
            ->sum('amount_iqd'), 2);
    }

    private function zhakoVault(): ?Vault
    {
        return Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();
    }

    private function auditEventCount(): int
    {
        if (! Schema::hasTable('activity_log')) {
            return 0;
        }

        return Activity::query()
            ->where('log_name', AuditActions::LOG_NAME)
            ->count();
    }
}
