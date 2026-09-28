<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Transaction;
use App\Models\Vault;
use App\Services\ExchangeRateService;
use App\Services\LiquidityService;
use Database\Seeders\VaultSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VaultDashboardController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly LiquidityService $liquidity,
    ) {}

    public function show(): Response
    {
        $this->authorize('viewDashboard', Vault::class);

        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        $rate = $this->exchangeRates->getUsdToIqd();
        $latestFx = ExchangeRate::query()
            ->where('base_currency', 'USD')
            ->where('target_currency', 'IQD')
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->first();

        $pending = $vault ? $this->liquidity->pendingPayoutsUsd($vault) : 0.0;
        $reserved = $vault ? $this->liquidity->reservedInsuranceUsd($vault) : 0.0;
        $available = $vault ? $this->liquidity->availableUsd($vault) : 0.0;

        $toIqd = fn (float $usd): float => round($usd * $rate, 0);

        $poolTotals = ProjectAllocation::query()
            ->selectRaw('
                COALESCE(SUM(expenses_pool_usd), 0) as expenses,
                COALESCE(SUM(payroll_pool_usd), 0) as payroll,
                COALESCE(SUM(retention_pool_usd), 0) as retention,
                COALESCE(SUM(penalty_pool_usd), 0) as penalty,
                COALESCE(SUM(profit_pool_usd), 0) as profit
            ')
            ->first();

        $holdStats = RetentionHold::query()
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(amount_usd), 0) as total')
            ->when($vault, fn ($q) => $q->where('vault_id', $vault->id))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $cashFlow = $this->cashFlowSeries($vault, 30, $rate);

        return Inertia::render('Dashboards/Vault', [
            'vault' => $vault ? [
                'id' => $vault->id,
                'name' => $vault->name,
                'balance_usd' => (float) $vault->balance_usd,
                'balance_iqd' => (float) $vault->balance_iqd,
            ] : null,
            'liquidity' => [
                'available_usd' => $available,
                'pending_payouts_usd' => $pending,
                'reserved_insurance_usd' => $reserved,
                'available_iqd' => $toIqd($available),
                'pending_payouts_iqd' => $toIqd($pending),
                'reserved_insurance_iqd' => $toIqd($reserved),
            ],
            // FX kept for ledger/payout plumbing + tests; not shown in UI (IQD-only product).
            'fx' => [
                'rate' => $rate,
                'source' => $latestFx?->source ?? ExchangeRateService::SOURCE_FALLBACK,
                'fetched_at' => $latestFx?->fetched_at?->toIso8601String(),
                'fallback_rate' => ExchangeRateService::FALLBACK_RATE,
            ],
            'insurance' => [
                'retention_pool_usd' => round((float) ($poolTotals->retention ?? 0), 2),
                'holding_usd' => round((float) ($holdStats->get(RetentionHold::STATUS_HOLDING)?->total ?? 0), 2),
                'holding_count' => (int) ($holdStats->get(RetentionHold::STATUS_HOLDING)?->cnt ?? 0),
                'matured_usd' => round((float) ($holdStats->get(RetentionHold::STATUS_MATURED)?->total ?? 0), 2),
                'matured_count' => (int) ($holdStats->get(RetentionHold::STATUS_MATURED)?->cnt ?? 0),
                'released_usd' => round((float) ($holdStats->get(RetentionHold::STATUS_RELEASED)?->total ?? 0), 2),
                'retention_pool_iqd' => $toIqd((float) ($poolTotals->retention ?? 0)),
                'holding_iqd' => $toIqd((float) ($holdStats->get(RetentionHold::STATUS_HOLDING)?->total ?? 0)),
                'matured_iqd' => $toIqd((float) ($holdStats->get(RetentionHold::STATUS_MATURED)?->total ?? 0)),
                'released_iqd' => $toIqd((float) ($holdStats->get(RetentionHold::STATUS_RELEASED)?->total ?? 0)),
            ],
            'pools' => [
                'expenses_usd' => round((float) ($poolTotals->expenses ?? 0), 2),
                'payroll_usd' => round((float) ($poolTotals->payroll ?? 0), 2),
                'retention_usd' => round((float) ($poolTotals->retention ?? 0), 2),
                'penalty_usd' => round((float) ($poolTotals->penalty ?? 0), 2),
                'profit_usd' => round((float) ($poolTotals->profit ?? 0), 2),
                'expenses_iqd' => $toIqd((float) ($poolTotals->expenses ?? 0)),
                'payroll_iqd' => $toIqd((float) ($poolTotals->payroll ?? 0)),
                'retention_iqd' => $toIqd((float) ($poolTotals->retention ?? 0)),
                'penalty_iqd' => $toIqd((float) ($poolTotals->penalty ?? 0)),
                'profit_iqd' => $toIqd((float) ($poolTotals->profit ?? 0)),
            ],
            'health' => $this->healthBadges($vault, $available, $pending, $reserved, $holdStats, $rate),
            'cashFlow' => $cashFlow,
        ]);
    }

    public function refreshFx(): RedirectResponse
    {
        $this->authorize('refreshFx', Vault::class);

        $rate = $this->exchangeRates->refresh();

        return back()->with('success', sprintf('FX refreshed: 1 USD = %s IQD', number_format($rate, 2)));
    }

    public function overrideFx(Request $request): RedirectResponse
    {
        $this->authorize('overrideFx', Vault::class);

        $data = $request->validate([
            'rate' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $rate = $this->exchangeRates->override((float) $data['rate'], $data['note'] ?? null);

        return back()->with('success', sprintf('FX overridden: 1 USD = %s IQD', number_format($rate, 2)));
    }

    /**
     * @param  \Illuminate\Support\Collection<string, object>  $holdStats
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    protected function healthBadges(
        ?Vault $vault,
        float $available,
        float $pending,
        float $reserved,
        $holdStats,
        float $rate,
    ): array {
        $balance = $vault ? (float) $vault->balance_usd : 0.0;
        $maturedCount = (int) ($holdStats->get(RetentionHold::STATUS_MATURED)?->cnt ?? 0);
        $fmt = fn (float $usd): string => number_format(round($usd * $rate, 0), 0);

        $liquidityStatus = 'critical';
        $liquidityDetail = __('health_no_liquidity');
        if ($vault && $balance > 0) {
            $ratio = $balance > 0 ? $available / $balance : 0;
            if ($available <= 0) {
                $liquidityStatus = 'critical';
                $liquidityDetail = __('health_available_zero');
            } elseif ($ratio < 0.15 || $pending > $available) {
                $liquidityStatus = 'warning';
                $liquidityDetail = __('health_available_pct', [
                    'amount' => $fmt($available),
                    'pct' => (int) round($ratio * 100),
                ]);
            } else {
                $liquidityStatus = 'healthy';
                $liquidityDetail = __('health_available', [
                    'amount' => $fmt($available),
                ]);
            }
        }

        $insuranceStatus = 'healthy';
        $insuranceDetail = __('health_reserved', ['amount' => $fmt($reserved)]);
        if ($maturedCount > 0) {
            $insuranceStatus = 'warning';
            $insuranceDetail = __('health_matured_holds', ['count' => $maturedCount]);
        } elseif ($reserved > 0 && $balance > 0 && $reserved / $balance > 0.35) {
            $insuranceStatus = 'warning';
            $insuranceDetail = __('health_insurance_large');
        }

        $poolSum = (float) ProjectAllocation::query()
            ->selectRaw('COALESCE(SUM(expenses_pool_usd + payroll_pool_usd + retention_pool_usd + penalty_pool_usd + profit_pool_usd), 0) as total')
            ->value('total');

        $allocStatus = 'critical';
        $allocDetail = __('health_no_allocations');
        if ($poolSum > 0) {
            $payroll = (float) ProjectAllocation::query()->sum('payroll_pool_usd');
            if ($payroll <= 0) {
                $allocStatus = 'warning';
                $allocDetail = __('health_payroll_depleted');
            } else {
                $allocStatus = 'healthy';
                $allocDetail = __('health_pools_total', ['amount' => $fmt($poolSum)]);
            }
        }

        return [
            [
                'key' => 'liquidity',
                'label' => __('health_liquidity'),
                'status' => $liquidityStatus,
                'detail' => $liquidityDetail,
            ],
            [
                'key' => 'insurance',
                'label' => __('health_insurance'),
                'status' => $insuranceStatus,
                'detail' => $insuranceDetail,
            ],
            [
                'key' => 'allocations',
                'label' => __('health_allocations'),
                'status' => $allocStatus,
                'detail' => $allocDetail,
            ],
        ];
    }

    /**
     * @return list<array{date: string, label: string, inflow_usd: float, outflow_usd: float, net_usd: float, inflow_iqd: float, outflow_iqd: float, net_iqd: float}>
     */
    protected function cashFlowSeries(?Vault $vault, int $days, float $rate): array
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays($days - 1);

        $rows = collect();
        if ($vault) {
            $rows = Transaction::query()
                ->where('vault_id', $vault->id)
                ->whereDate('created_at', '>=', $start)
                ->whereDate('created_at', '<=', $end)
                ->selectRaw("
                    DATE(created_at) as day,
                    SUM(CASE WHEN type IN (?, ?) THEN amount_usd ELSE 0 END) as inflow,
                    SUM(CASE WHEN type = ? THEN amount_usd ELSE 0 END) as outflow,
                    SUM(CASE WHEN type IN (?, ?) THEN amount_iqd ELSE 0 END) as inflow_iqd,
                    SUM(CASE WHEN type = ? THEN amount_iqd ELSE 0 END) as outflow_iqd
                ", [
                    Transaction::TYPE_DEPOSIT,
                    Transaction::TYPE_ADJUSTMENT,
                    Transaction::TYPE_WITHDRAWAL,
                    Transaction::TYPE_DEPOSIT,
                    Transaction::TYPE_ADJUSTMENT,
                    Transaction::TYPE_WITHDRAWAL,
                ])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('day')
                ->get()
                ->keyBy('day');
        }

        $series = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $row = $rows->get($key);
            $in = round((float) ($row->inflow ?? 0), 2);
            $out = round((float) ($row->outflow ?? 0), 2);
            $inIqd = round((float) ($row->inflow_iqd ?? ($in * $rate)), 0);
            $outIqd = round((float) ($row->outflow_iqd ?? ($out * $rate)), 0);
            $series[] = [
                'date' => $key,
                'label' => $d->format('M j'),
                'inflow_usd' => $in,
                'outflow_usd' => $out,
                'net_usd' => round($in - $out, 2),
                'inflow_iqd' => $inIqd,
                'outflow_iqd' => $outIqd,
                'net_iqd' => round($inIqd - $outIqd, 0),
            ];
        }

        return $series;
    }
}
