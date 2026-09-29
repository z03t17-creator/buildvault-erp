<?php

namespace App\Services;

use App\Models\Project;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\Vault;
use App\Support\Roles;
use Database\Seeders\VaultSeeder;

/**
 * Phase 14 — role-specific home dashboard payloads (real DB numbers, IQD).
 *
 * Each role only receives its slice; callers must gate by Spatie role.
 */
class RoleHomeDashboardService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly VaultLedgerService $ledger,
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
     * Quiet home shared by Super Admin + Boss + Accountant:
     * available cash, module charts, classify stripe.
     *
     * @return array<string, mixed>
     */
    private function quietHomeCore(): array
    {
        $vault = $this->zhakoVault();
        $balances = $vault ? $this->ledger->balances($vault) : null;
        $spend = $this->spendMixByCurrency($vault?->id);

        $unclassifiedPeople = \App\Models\Worker::query()
            ->where(function ($q) {
                $q->where('labor_kind', \App\Models\Worker::LABOR_KIND_UNCLASSIFIED)
                    ->orWhereNull('labor_kind');
            })
            ->count();

        return [
            'year_month' => now()->format('Y-m'),
            'available_iqd' => $balances['available_iqd'] ?? 0.0,
            'available_usd' => $balances['available_usd'] ?? 0.0,
            'reserved_iqd' => $balances['reserved_iqd'] ?? 0.0,
            'reserved_usd' => $balances['reserved_usd'] ?? 0.0,
            'pending_iqd' => $balances['pending_iqd'] ?? 0.0,
            'pending_usd' => $balances['pending_usd'] ?? 0.0,
            'charts' => [
                'available' => [
                    'usd' => (float) ($balances['available_usd'] ?? 0),
                    'iqd' => (float) ($balances['available_iqd'] ?? 0),
                ],
                'spend_usd' => $spend['usd'],
                'spend_iqd' => $spend['iqd'],
                'locked_free' => [
                    'usd' => [
                        'locked' => round(
                            (float) ($balances['reserved_usd'] ?? 0) + (float) ($balances['pending_usd'] ?? 0),
                            2
                        ),
                        'free' => (float) ($balances['available_usd'] ?? 0),
                    ],
                    'iqd' => [
                        'locked' => round(
                            (float) ($balances['reserved_iqd'] ?? 0) + (float) ($balances['pending_iqd'] ?? 0),
                            2
                        ),
                        'free' => (float) ($balances['available_iqd'] ?? 0),
                    ],
                ],
            ],
            'unclassified_people' => $unclassifiedPeople,
            'people' => \App\Models\Worker::query()->count(),
            'projects' => Project::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function superAdmin(): array
    {
        $bundledWorkbook = base_path('resources/imports/samples/hsabati-mayorca-zhako.xlsx');

        return [
            ...$this->quietHomeCore(),
            'workbook_bundled' => is_file($bundledWorkbook),
            'workbook_bytes' => is_file($bundledWorkbook) ? filesize($bundledWorkbook) : null,
        ];
    }

    /**
     * Native-currency spend buckets — never FX-blended across USD/IQD.
     *
     * @return array{usd: array{expenses: float, staff: float, salary: float}, iqd: array{expenses: float, staff: float, salary: float}}
     */
    private function spendMixByCurrency(?int $vaultId): array
    {
        $empty = ['expenses' => 0.0, 'staff' => 0.0, 'salary' => 0.0];
        if (! $vaultId) {
            return ['usd' => $empty, 'iqd' => $empty];
        }

        $buckets = [
            'expenses' => [Transaction::TYPE_EXPENSE, Transaction::TYPE_STOCK_PURCHASE],
            'staff' => [Transaction::TYPE_ADVANCE],
            'salary' => [Transaction::TYPE_PAYROLL],
        ];

        $usd = $empty;
        $iqd = $empty;

        foreach ($buckets as $key => $types) {
            $row = Transaction::query()
                ->forVault($vaultId)
                ->whereIn('type', $types)
                ->selectRaw('COALESCE(SUM(amount_usd), 0) as usd_total, COALESCE(SUM(amount_iqd), 0) as iqd_total')
                ->first();

            $usd[$key] = round((float) ($row->usd_total ?? 0), 2);
            $iqd[$key] = round((float) ($row->iqd_total ?? 0), 2);
        }

        return ['usd' => $usd, 'iqd' => $iqd];
    }

    /**
     * Boss / Contractor home — same quiet Available Cash + boxes + charts as Super Admin.
     * No Super-Admin-only workbook / Users / Mayorca / backups / audit payload.
     *
     * @return array<string, mixed>
     */
    private function boss(): array
    {
        return $this->quietHomeCore();
    }

    /**
     * Accountant home — quiet Available Cash + money-ops boxes + charts.
     * No KPI lecture dump, recent-tx table, Users, Mayorca, or backups payload.
     *
     * @return array<string, mixed>
     */
    private function accountant(): array
    {
        return $this->quietHomeCore();
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

    private function zhakoVault(): ?Vault
    {
        return Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();
    }
}
