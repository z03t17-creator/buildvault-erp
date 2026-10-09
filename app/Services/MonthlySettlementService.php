<?php

namespace App\Services;

use App\Models\MonthlySettlement;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use Database\Seeders\VaultSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 13 — Monthly Financial Settlement (IQD).
 *
 * Aggregates Phase 12 ledger types for a calendar month, surfaces real
 * LiquidityService available balance, and computes available money for payment.
 * Does not assume staff can always be paid.
 */
class MonthlySettlementService
{
    /** Types counted as money received in settlement. */
    public const MONEY_RECEIVED_TYPES = [
        Transaction::TYPE_MONEY_RECEIVED,
        Transaction::TYPE_DEPOSIT,
    ];

    /** Project / site expenses (cash out). */
    public const PROJECT_EXPENSE_TYPES = [
        Transaction::TYPE_EXPENSE,
    ];

    /** Other cash outflows not covered by payroll / advances / project expenses. */
    public const OTHER_EXPENSE_TYPES = [
        Transaction::TYPE_WITHDRAWAL,
        Transaction::TYPE_STOCK_PURCHASE,
        Transaction::TYPE_TRANSFER,
    ];

    public function __construct(
        private readonly VaultLedgerService $ledger,
    ) {}

    /**
     * Build a live settlement preview from the DB (not a saved snapshot).
     *
     * @return array{
     *     year_month: string,
     *     from: string,
     *     to: string,
     *     project_id: ?int,
     *     vault: array{id: int, name: string},
     *     money_received_iqd: float,
     *     available_vault_balance_iqd: float,
     *     project_expenses_iqd: float,
     *     payroll_iqd: float,
     *     employee_advances_iqd: float,
     *     insurance_iqd: float,
     *     penalties_iqd: float,
     *     other_expenses_iqd: float,
     *     approved_payments_iqd: float,
     *     available_money_for_payment_iqd: float,
     *     current_vault_iqd: float,
     *     pending_commitments_iqd: float,
     *     reserved_insurance_iqd: float,
     *     month_outflows_iqd: float,
     *     lines: list<array{key: string, amount_iqd: float, kind: string}>,
     *     ability: array{
     *         requested_payout_iqd: float,
     *         allowed: bool,
     *         shortfall_iqd: float,
     *         reasons: list<string>
     *     },
     *     snapshot: ?array<string, mixed>,
     *     projects: Collection<int, Project>,
     * }
     */
    public function preview(
        string $yearMonth,
        ?int $projectId = null,
        ?Vault $vault = null,
        float $requestedPayoutIqd = 0.0,
    ): array {
        $vault ??= $this->zhakoVault();
        [$from, $to] = $this->monthBounds($yearMonth);
        $projectId = $projectId && $projectId > 0 ? $projectId : null;

        $moneyReceived = $this->sumTypesDual($vault, self::MONEY_RECEIVED_TYPES, $from, $to, $projectId);
        $projectExpenses = $this->sumTypesDual($vault, self::PROJECT_EXPENSE_TYPES, $from, $to, $projectId);
        $payroll = $this->sumTypesDual($vault, [Transaction::TYPE_PAYROLL], $from, $to, $projectId);
        $advances = $this->sumTypesDual($vault, [Transaction::TYPE_ADVANCE], $from, $to, $projectId);
        $insurance = $this->sumTypesDual($vault, [Transaction::TYPE_INSURANCE], $from, $to, $projectId);
        $penalties = $this->sumTypesDual($vault, [Transaction::TYPE_PENALTY], $from, $to, $projectId);
        $otherExpenses = $this->sumTypesDual($vault, self::OTHER_EXPENSE_TYPES, $from, $to, $projectId);
        $approvedPayments = $this->approvedPaymentsDual($vault, $from, $to, $projectId);

        $balances = $this->ledger->balances($vault);
        $availableVaultIqd = (float) $balances['available_iqd'];
        $availableVaultUsd = (float) $balances['available_usd'];
        $currentVaultIqd = (float) $balances['current_iqd'];
        $currentVaultUsd = (float) $balances['current_usd'];
        $pendingIqd = (float) $balances['pending_iqd'];
        $pendingUsd = (float) $balances['pending_usd'];
        $reservedIqd = (float) $balances['reserved_iqd'];
        $reservedUsd = (float) $balances['reserved_usd'];

        $monthOutflowsIqd = round(
            $projectExpenses['iqd'] + $payroll['iqd'] + $advances['iqd'] + $otherExpenses['iqd'],
            2,
        );
        $monthOutflowsUsd = round(
            $projectExpenses['usd'] + $payroll['usd'] + $advances['usd'] + $otherExpenses['usd'],
            2,
        );

        // Real ability-to-pay: never invent cash. Available money for payment is
        // the live vault available balance (vault − pending − reserved insurance).
        // Native per currency — never FX-blend USD into IQD (or the reverse).
        $availableForPaymentIqd = $this->availableMoneyForPayment($availableVaultIqd);
        $availableForPaymentUsd = $this->availableMoneyForPayment($availableVaultUsd);

        $ability = $this->abilityToPay($availableForPaymentIqd, $requestedPayoutIqd);

        // Month ledger activity only — live vault balance stays in the hero, not here.
        $monthLines = [
            ['key' => 'money_received', 'amount_usd' => $moneyReceived['usd'], 'amount_iqd' => $moneyReceived['iqd'], 'kind' => 'in'],
            ['key' => 'project_expenses', 'amount_usd' => $projectExpenses['usd'], 'amount_iqd' => $projectExpenses['iqd'], 'kind' => 'out'],
            ['key' => 'payroll', 'amount_usd' => $payroll['usd'], 'amount_iqd' => $payroll['iqd'], 'kind' => 'out'],
            ['key' => 'employee_advances', 'amount_usd' => $advances['usd'], 'amount_iqd' => $advances['iqd'], 'kind' => 'out'],
            ['key' => 'insurance', 'amount_usd' => $insurance['usd'], 'amount_iqd' => $insurance['iqd'], 'kind' => 'accounting'],
            ['key' => 'penalties', 'amount_usd' => $penalties['usd'], 'amount_iqd' => $penalties['iqd'], 'kind' => 'accounting'],
            ['key' => 'other_expenses', 'amount_usd' => $otherExpenses['usd'], 'amount_iqd' => $otherExpenses['iqd'], 'kind' => 'out'],
            ['key' => 'approved_payments', 'amount_usd' => $approvedPayments['usd'], 'amount_iqd' => $approvedPayments['iqd'], 'kind' => 'out'],
        ];

        // Keep legacy keys for snapshots / older tests; UI must not treat live
        // vault balance as a month activity row (avoids bogus mega-totals).
        $lines = array_merge(
            [
                [
                    'key' => 'available_vault_balance',
                    'amount_usd' => $availableVaultUsd,
                    'amount_iqd' => $availableVaultIqd,
                    'kind' => 'balance',
                    'live' => true,
                ],
            ],
            $monthLines,
            [
                [
                    'key' => 'available_money_for_payment',
                    'amount_usd' => $availableForPaymentUsd,
                    'amount_iqd' => $availableForPaymentIqd,
                    'kind' => 'result',
                    'live' => true,
                ],
            ],
        );

        $snapshot = MonthlySettlement::query()
            ->where('vault_id', $vault->id)
            ->where('year_month', $yearMonth)
            ->where(function (Builder $q) use ($projectId) {
                if ($projectId === null) {
                    $q->whereNull('project_id');
                } else {
                    $q->where('project_id', $projectId);
                }
            })
            ->with(['creator:id,name', 'project:id,name'])
            ->first();

        return [
            'year_month' => $yearMonth,
            'from' => $from,
            'to' => $to,
            'project_id' => $projectId,
            'vault' => [
                'id' => $vault->id,
                'name' => $vault->name,
            ],
            'money_received_iqd' => $moneyReceived['iqd'],
            'money_received_usd' => $moneyReceived['usd'],
            'available_vault_balance_iqd' => $availableVaultIqd,
            'available_vault_balance_usd' => $availableVaultUsd,
            'project_expenses_iqd' => $projectExpenses['iqd'],
            'project_expenses_usd' => $projectExpenses['usd'],
            'payroll_iqd' => $payroll['iqd'],
            'payroll_usd' => $payroll['usd'],
            'employee_advances_iqd' => $advances['iqd'],
            'employee_advances_usd' => $advances['usd'],
            'insurance_iqd' => $insurance['iqd'],
            'insurance_usd' => $insurance['usd'],
            'penalties_iqd' => $penalties['iqd'],
            'penalties_usd' => $penalties['usd'],
            'other_expenses_iqd' => $otherExpenses['iqd'],
            'other_expenses_usd' => $otherExpenses['usd'],
            'approved_payments_iqd' => $approvedPayments['iqd'],
            'approved_payments_usd' => $approvedPayments['usd'],
            'available_money_for_payment_iqd' => $availableForPaymentIqd,
            'available_money_for_payment_usd' => $availableForPaymentUsd,
            'current_vault_iqd' => $currentVaultIqd,
            'current_vault_usd' => $currentVaultUsd,
            'pending_commitments_iqd' => $pendingIqd,
            'pending_commitments_usd' => $pendingUsd,
            'reserved_insurance_iqd' => $reservedIqd,
            'reserved_insurance_usd' => $reservedUsd,
            'month_outflows_iqd' => $monthOutflowsIqd,
            'month_outflows_usd' => $monthOutflowsUsd,
            'month_lines' => $monthLines,
            'lines' => $lines,
            'ability' => $ability,
            'snapshot' => $snapshot ? $this->serializeSnapshot($snapshot) : null,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * AVAILABLE MONEY FOR PAYMENT — never negative; equals real available vault balance.
     */
    public function availableMoneyForPayment(float $availableVaultBalanceIqd): float
    {
        return round(max(0, $availableVaultBalanceIqd), 2);
    }

    /**
     * Ability-to-pay check for a requested payout amount (IQD).
     *
     * @return array{
     *     requested_payout_iqd: float,
     *     allowed: bool,
     *     shortfall_iqd: float,
     *     reasons: list<string>
     * }
     */
    public function abilityToPay(float $availableForPaymentIqd, float $requestedPayoutIqd = 0.0): array
    {
        $available = round(max(0, $availableForPaymentIqd), 2);
        $requested = round(max(0, $requestedPayoutIqd), 2);
        $reasons = [];

        if ($requested > 0 && $requested > $available) {
            $reasons[] = sprintf(
                'Requested payout %.2f IQD exceeds available money for payment %.2f IQD.',
                $requested,
                $available,
            );
        }

        if ($available <= 0 && $requested > 0) {
            $reasons[] = 'Vault has no available money for payment after pending commitments and reserved insurance.';
        }

        $shortfall = $requested > $available ? round($requested - $available, 2) : 0.0;

        return [
            'requested_payout_iqd' => $requested,
            'allowed' => $reasons === [],
            'shortfall_iqd' => $shortfall,
            'reasons' => $reasons,
        ];
    }

    /**
     * Block when requested payouts exceed available money for payment.
     *
     * @throws InvalidArgumentException
     * @return array<string, mixed>
     */
    public function assertCanAfford(float $availableForPaymentIqd, float $requestedPayoutIqd): array
    {
        $result = $this->abilityToPay($availableForPaymentIqd, $requestedPayoutIqd);

        if (! $result['allowed']) {
            throw new InvalidArgumentException(implode(' ', $result['reasons']));
        }

        return $result;
    }

    /**
     * Persist an audit snapshot for the month (upsert per vault/month/project).
     *
     * @param  array{requested_payout_iqd?: float|int|string|null}  $options
     */
    public function saveSnapshot(
        string $yearMonth,
        ?int $projectId,
        User $actor,
        ?Vault $vault = null,
        array $options = [],
    ): MonthlySettlement {
        $requested = (float) ($options['requested_payout_iqd'] ?? 0);
        $preview = $this->preview($yearMonth, $projectId, $vault, $requested);

        // Saving a snapshot with a requested payout that exceeds available → block.
        if ($requested > 0) {
            $this->assertCanAfford(
                (float) $preview['available_money_for_payment_iqd'],
                $requested,
            );
        }

        $vaultId = $preview['vault']['id'];
        $projectId = $preview['project_id'];

        return DB::transaction(function () use ($preview, $vaultId, $projectId, $actor, $yearMonth) {
            $existing = MonthlySettlement::query()
                ->where('vault_id', $vaultId)
                ->where('year_month', $yearMonth)
                ->where(function (Builder $q) use ($projectId) {
                    if ($projectId === null) {
                        $q->whereNull('project_id');
                    } else {
                        $q->where('project_id', $projectId);
                    }
                })
                ->lockForUpdate()
                ->first();

            $attrs = [
                'vault_id' => $vaultId,
                'year_month' => $yearMonth,
                'project_id' => $projectId,
                'money_received_iqd' => $preview['money_received_iqd'],
                'available_vault_balance_iqd' => $preview['available_vault_balance_iqd'],
                'project_expenses_iqd' => $preview['project_expenses_iqd'],
                'payroll_iqd' => $preview['payroll_iqd'],
                'employee_advances_iqd' => $preview['employee_advances_iqd'],
                'insurance_iqd' => $preview['insurance_iqd'],
                'penalties_iqd' => $preview['penalties_iqd'],
                'other_expenses_iqd' => $preview['other_expenses_iqd'],
                'approved_payments_iqd' => $preview['approved_payments_iqd'],
                'available_money_for_payment_iqd' => $preview['available_money_for_payment_iqd'],
                'current_vault_iqd' => $preview['current_vault_iqd'],
                'pending_commitments_iqd' => $preview['pending_commitments_iqd'],
                'reserved_insurance_iqd' => $preview['reserved_insurance_iqd'],
                'payload' => [
                    'from' => $preview['from'],
                    'to' => $preview['to'],
                    'lines' => $preview['lines'],
                    'ability' => $preview['ability'],
                    'month_outflows_iqd' => $preview['month_outflows_iqd'],
                ],
                'created_by' => $actor->id,
            ];

            if ($existing) {
                $existing->fill($attrs);
                $existing->save();

                return $existing->fresh(['creator:id,name', 'project:id,name']);
            }

            return MonthlySettlement::query()->create($attrs)->load(['creator:id,name', 'project:id,name']);
        });
    }

    /**
     * @param  list<string>  $types
     * @return array{usd: float, iqd: float}
     */
    protected function sumTypesDual(
        Vault $vault,
        array $types,
        string $from,
        string $to,
        ?int $projectId,
    ): array {
        $query = Transaction::query()
            ->forVault($vault->id)
            ->whereIn('type', $types)
            ->whereDate('occurred_on', '>=', $from)
            ->whereDate('occurred_on', '<=', $to);

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        $row = $query->selectRaw('COALESCE(SUM(amount_usd), 0) as usd_sum, COALESCE(SUM(amount_iqd), 0) as iqd_sum')
            ->first();

        return [
            'usd' => round((float) ($row->usd_sum ?? 0), 2),
            'iqd' => round((float) ($row->iqd_sum ?? 0), 2),
        ];
    }

    /**
     * @param  list<string>  $types
     */
    protected function sumTypes(
        Vault $vault,
        array $types,
        string $from,
        string $to,
        ?int $projectId,
    ): float {
        return $this->sumTypesDual($vault, $types, $from, $to, $projectId)['iqd'];
    }

    /**
     * Approved + reconciled payouts posted in the month (native per currency).
     *
     * @return array{usd: float, iqd: float}
     */
    protected function approvedPaymentsDual(
        Vault $vault,
        string $from,
        string $to,
        ?int $projectId,
    ): array {
        $query = Payout::query()
            ->where('vault_id', $vault->id)
            ->whereIn('status', [Payout::STATUS_APPROVED, Payout::STATUS_RECONCILED])
            ->where(function (Builder $q) use ($from, $to) {
                $q->where(function (Builder $inner) use ($from, $to) {
                    $inner->whereNotNull('approved_at')
                        ->whereDate('approved_at', '>=', $from)
                        ->whereDate('approved_at', '<=', $to);
                })->orWhere(function (Builder $inner) use ($from, $to) {
                    $inner->whereNull('approved_at')
                        ->whereNotNull('reconciled_at')
                        ->whereDate('reconciled_at', '>=', $from)
                        ->whereDate('reconciled_at', '<=', $to);
                });
            });

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        $row = $query->selectRaw('COALESCE(SUM(amount_usd), 0) as usd_sum, COALESCE(SUM(amount_iqd), 0) as iqd_sum')
            ->first();

        return [
            'usd' => round((float) ($row->usd_sum ?? 0), 2),
            'iqd' => round((float) ($row->iqd_sum ?? 0), 2),
        ];
    }

    /**
     * Approved + reconciled payouts posted in the month (IQD).
     */
    protected function approvedPaymentsIqd(
        Vault $vault,
        string $from,
        string $to,
        ?int $projectId,
    ): float {
        return $this->approvedPaymentsDual($vault, $from, $to, $projectId)['iqd'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function monthBounds(string $yearMonth): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            throw new InvalidArgumentException('Month must be YYYY-MM.');
        }

        $start = $yearMonth.'-01';
        if (! checkdate((int) substr($yearMonth, 5, 2), 1, (int) substr($yearMonth, 0, 4))) {
            throw new InvalidArgumentException("Invalid month [{$yearMonth}].");
        }

        $end = date('Y-m-t', strtotime($start));

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeSnapshot(MonthlySettlement $row): array
    {
        return [
            'id' => $row->id,
            'year_month' => $row->year_month,
            'project_id' => $row->project_id,
            'project' => $row->project
                ? ['id' => $row->project->id, 'name' => $row->project->name]
                : null,
            'available_money_for_payment_iqd' => round((float) $row->available_money_for_payment_iqd, 2),
            'money_received_iqd' => round((float) $row->money_received_iqd, 2),
            'saved_at' => optional($row->updated_at)?->toIso8601String(),
            'saved_by' => $row->creator
                ? ['id' => $row->creator->id, 'name' => $row->creator->name]
                : null,
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
