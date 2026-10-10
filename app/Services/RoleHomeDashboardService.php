<?php

namespace App\Services;

use App\Models\Project;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Models\Worker;
use App\Support\DualCurrency;
use App\Support\Roles;
use Carbon\Carbon;
use Database\Seeders\VaultSeeder;

/**
 * Role-specific home dashboard payloads (real DB numbers; USD/IQD never blended).
 */
class RoleHomeDashboardService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly VaultLedgerService $ledger,
        private readonly SimpleVaultService $simpleVault,
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
     * Shared finance home for Super Admin + Boss + Accountant.
     *
     * @return array<string, mixed>
     */
    private function quietHomeCore(): array
    {
        $vault = $this->zhakoVault();
        $balances = $vault ? $this->ledger->balances($vault) : null;
        $spend = $this->spendMixByCurrency($vault?->id);
        $simpleSpend = $this->vaultLineSpendMix($vault?->id);
        $snap = $vault ? $this->simpleVault->dashboardSnapshot($vault) : null;
        $stockSummary = $this->stock->dashboardSummary();

        $availableUsd = (float) ($snap['available_cash']['USD'] ?? $balances['available_usd'] ?? 0);
        $availableIqd = (float) ($snap['available_cash']['IQD'] ?? $balances['available_iqd'] ?? 0);
        $staffLockedUsd = (float) ($snap['estimates']['USD']['staff_owed_held'] ?? 0);
        $staffLockedIqd = (float) ($snap['estimates']['IQD']['staff_owed_held'] ?? 0);
        $insuranceUsd = (float) ($snap['estimates']['USD']['company_insurance_held'] ?? 0);
        $insuranceIqd = (float) ($snap['estimates']['IQD']['company_insurance_held'] ?? 0);

        $expenseUsd = round(
            ($simpleSpend['usd']['staff'] ?? 0)
            + ($simpleSpend['usd']['materials'] ?? 0)
            + ($simpleSpend['usd']['salary'] ?? 0),
            2
        );
        $expenseIqd = round(
            ($simpleSpend['iqd']['staff'] ?? 0)
            + ($simpleSpend['iqd']['materials'] ?? 0)
            + ($simpleSpend['iqd']['salary'] ?? 0),
            2
        );

        $cashTrend = $this->cashTrendPercent($vault?->id);

        $unclassifiedPeople = Worker::query()
            ->where(function ($q) {
                $q->where('labor_kind', Worker::LABOR_KIND_UNCLASSIFIED)
                    ->orWhereNull('labor_kind');
            })
            ->count();

        return [
            'year_month' => now()->format('Y-m'),
            'available_iqd' => $availableIqd,
            'available_usd' => $availableUsd,
            'reserved_iqd' => $balances['reserved_iqd'] ?? 0.0,
            'reserved_usd' => $balances['reserved_usd'] ?? 0.0,
            'pending_iqd' => $balances['pending_iqd'] ?? 0.0,
            'pending_usd' => $balances['pending_usd'] ?? 0.0,
            'finance' => [
                'available' => [
                    'usd' => $availableUsd,
                    'iqd' => $availableIqd,
                    'trend_pct' => $cashTrend,
                ],
                'locked' => [
                    'staff_usd' => $staffLockedUsd,
                    'staff_iqd' => $staffLockedIqd,
                    'insurance_usd' => $insuranceUsd,
                    'insurance_iqd' => $insuranceIqd,
                    'total_usd' => round($staffLockedUsd + $insuranceUsd, 2),
                    'total_iqd' => round($staffLockedIqd + $insuranceIqd, 2),
                ],
                'expenses' => [
                    'usd' => $expenseUsd,
                    'iqd' => $expenseIqd,
                    'staff_usd' => $simpleSpend['usd']['staff'],
                    'staff_iqd' => $simpleSpend['iqd']['staff'],
                    'materials_usd' => $simpleSpend['usd']['materials'],
                    'materials_iqd' => $simpleSpend['iqd']['materials'],
                    'salary_usd' => $simpleSpend['usd']['salary'],
                    'salary_iqd' => $simpleSpend['iqd']['salary'],
                ],
                'inventory' => [
                    'usd' => (float) ($stockSummary['stock_value_usd'] ?? 0),
                    'iqd' => (float) ($stockSummary['stock_value_iqd'] ?? 0),
                    'items' => (int) ($stockSummary['total_items'] ?? 0),
                ],
                'cashflow' => $this->cashflowSeries($vault?->id, 30),
                'expense_donut' => [
                    'staff' => round(
                        ($simpleSpend['usd']['staff'] ?? 0) + ($simpleSpend['iqd']['staff'] ?? 0),
                        2
                    ),
                    'materials' => round(
                        ($simpleSpend['usd']['materials'] ?? 0) + ($simpleSpend['iqd']['materials'] ?? 0),
                        2
                    ),
                    'salary' => round(
                        ($simpleSpend['usd']['salary'] ?? 0) + ($simpleSpend['iqd']['salary'] ?? 0),
                        2
                    ),
                    // Native legs kept for dual-currency display in tooltips
                    'legs' => $simpleSpend,
                ],
                'activity' => $this->recentActivity($vault?->id, 12),
            ],
            'charts' => [
                'available' => [
                    'usd' => $availableUsd,
                    'iqd' => $availableIqd,
                ],
                'spend_usd' => [
                    'expenses' => $simpleSpend['usd']['materials'],
                    'staff' => $simpleSpend['usd']['staff'],
                    'salary' => $simpleSpend['usd']['salary'],
                ],
                'spend_iqd' => [
                    'expenses' => $simpleSpend['iqd']['materials'],
                    'staff' => $simpleSpend['iqd']['staff'],
                    'salary' => $simpleSpend['iqd']['salary'],
                ],
                'locked_free' => [
                    'usd' => [
                        'locked' => round($staffLockedUsd + $insuranceUsd, 2),
                        'free' => $availableUsd,
                    ],
                    'iqd' => [
                        'locked' => round($staffLockedIqd + $insuranceIqd, 2),
                        'free' => $availableIqd,
                    ],
                ],
            ],
            'unclassified_people' => $unclassifiedPeople,
            'people' => Worker::query()->count(),
            'projects' => Project::query()->count(),
            // Legacy transaction spend kept for debugging / older consumers
            'legacy_spend' => $spend,
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
     * Spend from vault_lines (simple vault source of truth).
     *
     * @return array{usd: array{staff: float, materials: float, salary: float}, iqd: array{staff: float, materials: float, salary: float}}
     */
    private function vaultLineSpendMix(?int $vaultId): array
    {
        $empty = ['staff' => 0.0, 'materials' => 0.0, 'salary' => 0.0];
        if (! $vaultId) {
            return ['usd' => $empty, 'iqd' => $empty];
        }

        $rows = VaultLine::query()
            ->where('vault_id', $vaultId)
            ->whereIn('kind', [
                VaultLine::KIND_JOB_PAY,
                VaultLine::KIND_DAILY_PAY,
                VaultLine::KIND_UNIT_PAY,
                VaultLine::KIND_SALARY,
                VaultLine::KIND_EXPENSE,
            ])
            ->selectRaw('kind, currency, COALESCE(SUM(amount), 0) as total')
            ->groupBy('kind', 'currency')
            ->get();

        $out = ['usd' => $empty, 'iqd' => $empty];
        foreach ($rows as $row) {
            $currency = strtoupper((string) $row->currency) === DualCurrency::USD ? 'usd' : 'iqd';
            $total = round((float) $row->total, 2);
            $bucket = match ($row->kind) {
                VaultLine::KIND_SALARY => 'salary',
                VaultLine::KIND_EXPENSE => 'materials',
                default => 'staff',
            };
            $out[$currency][$bucket] = round($out[$currency][$bucket] + $total, 2);
        }

        return $out;
    }

    /**
     * Month-over-month net cashflow change (advances − outflows) as percent.
     */
    private function cashTrendPercent(?int $vaultId): ?float
    {
        if (! $vaultId) {
            return null;
        }

        $thisMonthStart = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $netFor = function (string $from, string $to) use ($vaultId): float {
            $in = (float) VaultLine::query()
                ->where('vault_id', $vaultId)
                ->where('kind', VaultLine::KIND_ADVANCE)
                ->whereDate('occurred_on', '>=', $from)
                ->whereDate('occurred_on', '<=', $to)
                ->sum('amount');
            $out = (float) VaultLine::query()
                ->where('vault_id', $vaultId)
                ->whereIn('kind', [
                    VaultLine::KIND_JOB_PAY,
                    VaultLine::KIND_DAILY_PAY,
                    VaultLine::KIND_UNIT_PAY,
                    VaultLine::KIND_SALARY,
                    VaultLine::KIND_EXPENSE,
                ])
                ->whereDate('occurred_on', '>=', $from)
                ->whereDate('occurred_on', '<=', $to)
                ->sum('amount');

            return round($in - $out, 2);
        };

        $current = $netFor($thisMonthStart, now()->toDateString());
        $previous = $netFor($lastMonthStart, $lastMonthEnd);

        if (abs($previous) < 0.01) {
            return $current > 0 ? 100.0 : ($current < 0 ? -100.0 : 0.0);
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * @return list<array{date: string, advances: float, expenses: float}>
     */
    private function cashflowSeries(?int $vaultId, int $days = 30): array
    {
        if (! $vaultId) {
            return [];
        }

        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $advances = VaultLine::query()
            ->where('vault_id', $vaultId)
            ->where('kind', VaultLine::KIND_ADVANCE)
            ->whereDate('occurred_on', '>=', $start->toDateString())
            ->whereDate('occurred_on', '<=', $end->toDateString())
            ->selectRaw('DATE(occurred_on) as d, COALESCE(SUM(amount), 0) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $expenses = VaultLine::query()
            ->where('vault_id', $vaultId)
            ->whereIn('kind', [
                VaultLine::KIND_JOB_PAY,
                VaultLine::KIND_DAILY_PAY,
                VaultLine::KIND_UNIT_PAY,
                VaultLine::KIND_SALARY,
                VaultLine::KIND_EXPENSE,
            ])
            ->whereDate('occurred_on', '>=', $start->toDateString())
            ->whereDate('occurred_on', '<=', $end->toDateString())
            ->selectRaw('DATE(occurred_on) as d, COALESCE(SUM(amount), 0) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'label' => Carbon::parse($day)->format('m-d'),
                'advances' => round((float) ($advances[$day] ?? 0), 2),
                'expenses' => round((float) ($expenses[$day] ?? 0), 2),
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(?int $vaultId, int $limit = 12): array
    {
        if (! $vaultId) {
            return [];
        }

        return VaultLine::query()
            ->with(['staff:id,name', 'project:id,name'])
            ->where('vault_id', $vaultId)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (VaultLine $line) {
                $kind = (string) $line->kind;
                $title = match ($kind) {
                    VaultLine::KIND_ADVANCE => 'advance',
                    VaultLine::KIND_EXPENSE => 'expense',
                    VaultLine::KIND_SALARY => 'salary',
                    VaultLine::KIND_JOB_PAY, VaultLine::KIND_DAILY_PAY, VaultLine::KIND_UNIT_PAY => 'staff_pay',
                    default => $kind,
                };
                $recipient = $line->staff?->name
                    ?: ($line->purpose ?: ($line->note ?: null));

                $status = 'posted';
                if ((float) $line->hold_amount > 0 && $line->hold_released_at === null) {
                    $status = 'held';
                }

                return [
                    'id' => $line->id,
                    'date' => $line->occurred_on?->toDateString(),
                    'kind' => $kind,
                    'title_key' => $title,
                    'recipient' => $recipient,
                    'project' => $line->project?->name,
                    'apartment' => $line->apartment_number ?: $line->villa_number,
                    'amount' => round((float) $line->amount, 2),
                    'currency' => strtoupper((string) $line->currency),
                    'status' => $status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function boss(): array
    {
        return $this->quietHomeCore();
    }

    /**
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

        $items = StockItem::query()->get(['id', 'category', 'quantity', 'min_quantity', 'purchase_price_iqd', 'purchase_price_usd', 'currency']);

        $byCategory = $items
            ->groupBy(function (StockItem $item) {
                $cat = trim((string) ($item->category ?? ''));

                return $cat !== '' ? $cat : 'uncategorized';
            })
            ->map(function ($group, $category) {
                return [
                    'category' => $category,
                    'items_count' => $group->count(),
                    'quantity_total' => round((float) $group->sum(fn (StockItem $i) => (float) $i->quantity), 3),
                    'value_iqd' => round((float) $group->sum(fn (StockItem $i) => $i->stockValueIqd()), 2),
                ];
            })
            ->sortByDesc('value_iqd')
            ->values()
            ->all();

        $okStock = $items->filter(
            fn (StockItem $i) => ! $i->isLowStock() && ! $i->isOutOfStock()
        )->count();

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

        $categoryBars = collect($byCategory)
            ->take(6)
            ->map(fn (array $c) => [
                'key' => $c['category'],
                'label' => $c['category'],
                'value' => $c['value_iqd'],
            ])
            ->values()
            ->all();

        return [
            'total_items' => $summary['total_items'],
            'stock_value_iqd' => $summary['stock_value_iqd'],
            'stock_value_usd' => $summary['stock_value_usd'],
            'low_stock' => $summary['low_stock'],
            'out_of_stock' => $summary['out_of_stock'],
            'today_in_qty' => $summary['today_in_qty'],
            'today_out_qty' => $summary['today_out_qty'],
            'today_in_count' => $summary['today_in_count'],
            'today_out_count' => $summary['today_out_count'],
            'by_category' => $byCategory,
            'recent_movements' => $recent,
            'charts' => [
                'today_flow' => [
                    'in' => (float) $summary['today_in_qty'],
                    'out' => (float) $summary['today_out_qty'],
                ],
                'health' => [
                    'ok' => $okStock,
                    'low' => (int) $summary['low_stock'],
                    'out' => (int) $summary['out_of_stock'],
                ],
                'value_by_category' => $categoryBars,
            ],
        ];
    }

    private function zhakoVault(): ?Vault
    {
        return Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();
    }
}
