<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Transaction;
use App\Models\Vault;
use Database\Seeders\VaultSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Phase 12 — real business transaction ledger (list + balances).
 *
 * Pool allocation remains optional/secondary; this service reads cash and
 * accounting rows from `transactions` and computes current / available /
 * reserved balances against the vault + LiquidityService.
 */
class VaultLedgerService
{
    public function __construct(
        private readonly LiquidityService $liquidity,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * @param  array{
     *     type?: ?string,
     *     project_id?: ?int,
     *     user_id?: ?int,
     *     from?: ?string,
     *     to?: ?string,
     *     q?: ?string,
     *     include_non_cash?: bool|string|int|null,
     *     per_page?: int,
     * }  $filters
     * @return array{
     *     vault: Vault,
     *     balances: array{
     *         current_iqd: float,
     *         available_iqd: float,
     *         reserved_iqd: float,
     *         pending_iqd: float,
     *         current_usd: float,
     *         available_usd: float,
     *         reserved_usd: float,
     *         pending_usd: float,
     *         ledger_cash_iqd: float,
     *         balance_matches_ledger: bool,
     *     },
     *     transactions: LengthAwarePaginator,
     *     filters: array<string, mixed>,
     *     types: list<string>,
     *     projects: Collection<int, Project>,
     * }
     */
    public function listing(?Vault $vault = null, array $filters = []): array
    {
        $vault ??= $this->zhakoVault();
        $balances = $this->balances($vault);

        $includeNonCash = filter_var($filters['include_non_cash'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $type = isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== ''
            ? $filters['type']
            : null;
        $projectId = isset($filters['project_id']) && (int) $filters['project_id'] > 0
            ? (int) $filters['project_id']
            : null;
        $userId = isset($filters['user_id']) && (int) $filters['user_id'] > 0
            ? (int) $filters['user_id']
            : null;
        $from = isset($filters['from']) && is_string($filters['from']) && $filters['from'] !== ''
            ? $filters['from']
            : null;
        $to = isset($filters['to']) && is_string($filters['to']) && $filters['to'] !== ''
            ? $filters['to']
            : null;
        $q = isset($filters['q']) && is_string($filters['q']) ? trim($filters['q']) : '';
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 50)));

        $query = $this->baseQuery($vault)
            ->with([
                'project:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');

        if ($type !== null) {
            if (! in_array($type, Transaction::TYPES, true)) {
                throw new InvalidArgumentException("Unknown ledger type [{$type}].");
            }
            $query->where('type', $type);
        } elseif (! $includeNonCash) {
            $query->cashAffecting();
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($userId) {
            $query->where('created_by', $userId);
        }
        if ($from) {
            $query->whereDate('occurred_on', '>=', $from);
        }
        if ($to) {
            $query->whereDate('occurred_on', '<=', $to);
        }
        if ($q !== '') {
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('description', 'like', '%'.$q.'%')
                    ->orWhere('reference_code', 'like', '%'.$q.'%');
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Transaction $txn) => $this->serialize($txn))
        );

        return [
            'vault' => $vault,
            'balances' => $balances,
            'transactions' => $paginator,
            'filters' => [
                'type' => $type ?? '',
                'project_id' => $projectId,
                'user_id' => $userId,
                'from' => $from ?? '',
                'to' => $to ?? '',
                'q' => $q,
                'include_non_cash' => $includeNonCash,
                'per_page' => $perPage,
            ],
            'types' => Transaction::TYPES,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Dual-currency balances — USD and IQD from native columns only.
     * Never FX-converts available/reserved/pending across currencies.
     *
     * @return array{
     *     current_iqd: float,
     *     available_iqd: float,
     *     reserved_iqd: float,
     *     pending_iqd: float,
     *     current_usd: float,
     *     available_usd: float,
     *     reserved_usd: float,
     *     pending_usd: float,
     *     ledger_cash_iqd: float,
     *     ledger_cash_usd: float,
     *     balance_matches_ledger: bool,
     *     balance_matches_ledger_usd: bool,
     * }
     */
    public function balances(?Vault $vault = null, ?float $rate = null): array
    {
        $vault ??= $this->zhakoVault();
        // $rate retained for call-site compatibility; intentionally unused for blend.
        unset($rate);

        $snap = $this->liquidity->dualSnapshot($vault);

        $ledgerCashIqd = $this->ledgerCashBalanceIqd($vault);
        $ledgerCashUsd = $this->ledgerCashBalanceUsd($vault);
        $currentUsd = $snap['balance_usd'];
        $currentIqd = $snap['balance_iqd'];

        return [
            'current_iqd' => $currentIqd,
            'available_iqd' => $snap['available_iqd'],
            'reserved_iqd' => $snap['reserved_iqd'],
            'pending_iqd' => $snap['pending_iqd'],
            'current_usd' => $currentUsd,
            'available_usd' => $snap['available_usd'],
            'reserved_usd' => $snap['reserved_usd'],
            'pending_usd' => $snap['pending_usd'],
            'ledger_cash_iqd' => $ledgerCashIqd,
            'ledger_cash_usd' => $ledgerCashUsd,
            'balance_matches_ledger' => abs($ledgerCashIqd - $currentIqd) < 1.0,
            'balance_matches_ledger_usd' => abs($ledgerCashUsd - $currentUsd) < 0.01,
        ];
    }

    /**
     * Sum signed cash-affecting ledger IQD (should match vault.balance_iqd).
     */
    public function ledgerCashBalanceIqd(?Vault $vault = null): float
    {
        $vault ??= $this->zhakoVault();

        $in = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_INFLOW_TYPES)
            ->sum('amount_iqd');

        $out = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_OUTFLOW_TYPES)
            ->sum('amount_iqd');

        return round($in - $out, 2);
    }

    /**
     * Sum signed cash-affecting ledger USD (should match vault.balance_usd).
     */
    public function ledgerCashBalanceUsd(?Vault $vault = null): float
    {
        $vault ??= $this->zhakoVault();

        $in = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_INFLOW_TYPES)
            ->sum('amount_usd');

        $out = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_OUTFLOW_TYPES)
            ->sum('amount_usd');

        return round($in - $out, 2);
    }

    /**
     * Phase 13 hook — monthly settlement can call this without new surface yet.
     *
     * @return array{from: string, to: string, opening_iqd: float, closing_iqd: float, inflow_iqd: float, outflow_iqd: float}
     */
    public function monthlySettlementPreview(string $yearMonth, ?Vault $vault = null): array
    {
        $vault ??= $this->zhakoVault();
        $start = $yearMonth.'-01';
        $end = date('Y-m-t', strtotime($start));

        $inflow = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_INFLOW_TYPES)
            ->whereDate('occurred_on', '>=', $start)
            ->whereDate('occurred_on', '<=', $end)
            ->sum('amount_iqd');

        $outflow = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_OUTFLOW_TYPES)
            ->whereDate('occurred_on', '>=', $start)
            ->whereDate('occurred_on', '<=', $end)
            ->sum('amount_iqd');

        $priorIn = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_INFLOW_TYPES)
            ->whereDate('occurred_on', '<', $start)
            ->sum('amount_iqd');

        $priorOut = (float) Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', Transaction::CASH_OUTFLOW_TYPES)
            ->whereDate('occurred_on', '<', $start)
            ->sum('amount_iqd');

        $opening = round($priorIn - $priorOut, 2);
        $closing = round($opening + $inflow - $outflow, 2);

        return [
            'from' => $start,
            'to' => $end,
            'opening_iqd' => $opening,
            'closing_iqd' => $closing,
            'inflow_iqd' => round($inflow, 2),
            'outflow_iqd' => round($outflow, 2),
        ];
    }

    /**
     * @return Builder<Transaction>
     */
    protected function baseQuery(Vault $vault): Builder
    {
        return Transaction::query()->forVault($vault->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Transaction $txn): array
    {
        $signed = $txn->signedAmountIqd();

        return [
            'id' => $txn->id,
            'date' => optional($txn->occurred_on)->toDateString()
                ?? optional($txn->created_at)->toDateString(),
            'created_at' => optional($txn->created_at)?->toIso8601String(),
            'amount_iqd' => round((float) $txn->amount_iqd, 2),
            'amount_usd' => round((float) $txn->amount_usd, 2),
            'signed_amount_iqd' => $signed,
            'direction' => $signed > 0 ? 'in' : ($signed < 0 ? 'out' : 'none'),
            'type' => $txn->type,
            'project' => $txn->project
                ? ['id' => $txn->project->id, 'name' => $txn->project->name]
                : null,
            'user' => $txn->creator
                ? ['id' => $txn->creator->id, 'name' => $txn->creator->name]
                : null,
            'reference' => $txn->reference_code
                ?: ($txn->reference_type && $txn->reference_id
                    ? class_basename($txn->reference_type).' #'.$txn->reference_id
                    : null),
            'reference_code' => $txn->reference_code,
            'reference_type' => $txn->reference_type,
            'reference_id' => $txn->reference_id,
            'description' => $txn->description,
            'exchange_rate' => round((float) $txn->exchange_rate, 4),
            'is_non_cash' => $txn->isNonCash(),
        ];
    }

    protected function zhakoVault(): Vault
    {
        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first();

        if (! $vault) {
            throw new InvalidArgumentException('No vault found. Seed the Zhako vault first.');
        }

        return $vault;
    }
}
