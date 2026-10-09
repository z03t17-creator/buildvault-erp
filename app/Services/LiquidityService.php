<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Vault;
use App\Support\DualCurrency;
use Database\Seeders\VaultSeeder;
use InvalidArgumentException;

class LiquidityService
{
    /**
     * Map payout category → project_allocations column.
     *
     * @var array<string, string>
     */
    public const CATEGORY_POOL_COLUMNS = [
        Payout::CATEGORY_EXPENSES => 'expenses_pool_usd',
        Payout::CATEGORY_PAYROLL => 'payroll_pool_usd',
        Payout::CATEGORY_RETENTION => 'retention_pool_usd',
        Payout::CATEGORY_PENALTY => 'penalty_pool_usd',
        Payout::CATEGORY_PROFIT => 'profit_pool_usd',
    ];

    /**
     * Available = Vault − (Pending Payouts + Pending Expenses + Reserved Insurance).
     */
    public function availableUsd(?Vault $vault = null, ?int $excludeExpenseId = null): float
    {
        $vault ??= $this->zhakoVault();

        $pending = $this->pendingCommitmentsUsd($vault, $excludeExpenseId);
        $reserved = $this->reservedInsuranceUsd($vault);

        return round(max(0, (float) $vault->balance_usd - $pending - $reserved), 2);
    }

    /**
     * Pending payouts + pending expenses (committed, not yet paid out).
     */
    public function pendingCommitmentsUsd(?Vault $vault = null, ?int $excludeExpenseId = null): float
    {
        return round(
            $this->pendingPayoutsUsd($vault) + $this->pendingExpensesUsd($vault, $excludeExpenseId),
            2,
        );
    }

    /**
     * Sum of pending payout amounts against the vault (committed, not yet paid out).
     */
    public function pendingPayoutsUsd(?Vault $vault = null): float
    {
        $vault ??= $this->zhakoVault();

        $sum = Payout::query()
            ->where('vault_id', $vault->id)
            ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_HELD])
            ->sum('amount_usd');

        return round((float) $sum, 2);
    }

    /**
     * Sum of pending project expenses against the vault.
     */
    public function pendingExpensesUsd(?Vault $vault = null, ?int $excludeExpenseId = null): float
    {
        $vault ??= $this->zhakoVault();

        $query = Expense::query()
            ->where('vault_id', $vault->id)
            ->whereIn('approval_status', [Expense::STATUS_PENDING, Expense::STATUS_HELD]);

        if ($excludeExpenseId !== null) {
            $query->where('id', '!=', $excludeExpenseId);
        }

        return round((float) $query->sum('amount_usd'), 2);
    }

    /**
     * Insurance still locked in retention holds (holding + matured, not released).
     */
    public function reservedInsuranceUsd(?Vault $vault = null): float
    {
        $vault ??= $this->zhakoVault();

        $sum = RetentionHold::query()
            ->where('vault_id', $vault->id)
            ->whereIn('status', [
                RetentionHold::STATUS_HOLDING,
                RetentionHold::STATUS_MATURED,
            ])
            ->sum('amount_usd');

        $client = 0.0;
        if (class_exists(\App\Models\ClientRetentionHold::class)) {
            $client = (float) \App\Models\ClientRetentionHold::query()
                ->where('vault_id', $vault->id)
                ->whereIn('status', [
                    \App\Models\ClientRetentionHold::STATUS_HOLDING,
                    \App\Models\ClientRetentionHold::STATUS_MATURED,
                ])
                ->sum('amount_usd');
        }

        return round((float) $sum + $client, 2);
    }

    /**
     * Native IQD insurance reserved (never FX-converted from USD).
     */
    public function reservedInsuranceIqd(?Vault $vault = null): float
    {
        $vault ??= $this->zhakoVault();

        $sum = RetentionHold::query()
            ->where('vault_id', $vault->id)
            ->whereIn('status', [
                RetentionHold::STATUS_HOLDING,
                RetentionHold::STATUS_MATURED,
            ])
            ->sum('amount_iqd');

        $client = (float) \App\Models\ClientRetentionHold::query()
            ->where('vault_id', $vault->id)
            ->whereIn('status', [
                \App\Models\ClientRetentionHold::STATUS_HOLDING,
                \App\Models\ClientRetentionHold::STATUS_MATURED,
            ])
            ->sum('amount_iqd');

        return round((float) $sum + $client, 2);
    }

    /**
     * Pending payouts + pending expenses in native IQD (no FX blend).
     */
    public function pendingCommitmentsIqd(?Vault $vault = null, ?int $excludeExpenseId = null): float
    {
        return round(
            $this->pendingPayoutsIqd($vault) + $this->pendingExpensesIqd($vault, $excludeExpenseId),
            2,
        );
    }

    public function pendingPayoutsIqd(?Vault $vault = null): float
    {
        $vault ??= $this->zhakoVault();

        $sum = Payout::query()
            ->where('vault_id', $vault->id)
            ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_HELD])
            ->sum('amount_iqd');

        return round((float) $sum, 2);
    }

    public function pendingExpensesIqd(?Vault $vault = null, ?int $excludeExpenseId = null): float
    {
        $vault ??= $this->zhakoVault();

        $query = Expense::query()
            ->where('vault_id', $vault->id)
            ->whereIn('approval_status', [Expense::STATUS_PENDING, Expense::STATUS_HELD]);

        if ($excludeExpenseId !== null) {
            $query->where('id', '!=', $excludeExpenseId);
        }

        return round((float) $query->sum('amount_iqd'), 2);
    }

    /**
     * Available IQD = Vault IQD − pending IQD − reserved IQD (native columns only).
     */
    public function availableIqd(?Vault $vault = null, ?int $excludeExpenseId = null): float
    {
        $vault ??= $this->zhakoVault();

        $pending = $this->pendingCommitmentsIqd($vault, $excludeExpenseId);
        $reserved = $this->reservedInsuranceIqd($vault);

        return round(max(0, (float) $vault->balance_iqd - $pending - $reserved), 2);
    }

    /**
     * Dual snapshot — USD and IQD isolated (never summed or FX-blended).
     *
     * @return array{
     *     balance_usd: float,
     *     balance_iqd: float,
     *     available_usd: float,
     *     available_iqd: float,
     *     pending_usd: float,
     *     pending_iqd: float,
     *     reserved_usd: float,
     *     reserved_iqd: float,
     * }
     */
    public function dualSnapshot(?Vault $vault = null, ?int $excludeExpenseId = null): array
    {
        $vault ??= $this->zhakoVault();

        return [
            'balance_usd' => round((float) $vault->balance_usd, 2),
            'balance_iqd' => round((float) $vault->balance_iqd, 2),
            'available_usd' => $this->availableUsd($vault, $excludeExpenseId),
            'available_iqd' => $this->availableIqd($vault, $excludeExpenseId),
            'pending_usd' => $this->pendingCommitmentsUsd($vault, $excludeExpenseId),
            'pending_iqd' => $this->pendingCommitmentsIqd($vault, $excludeExpenseId),
            'reserved_usd' => $this->reservedInsuranceUsd($vault),
            'reserved_iqd' => $this->reservedInsuranceIqd($vault),
        ];
    }

    /**
     * Remaining USD in a project's category pool (0 if no allocation row).
     */
    public function poolAvailableUsd(Project $project, string $category): float
    {
        $column = $this->poolColumn($category);
        $allocation = ProjectAllocation::query()->where('project_id', $project->id)->first();

        if (! $allocation) {
            return 0.0;
        }

        return round((float) $allocation->{$column}, 2);
    }

    /**
     * Ability-to-pay check: vault liquidity + category pool.
     *
     * @return array{
     *     allowed: bool,
     *     amount_usd: float,
     *     available_usd: float,
     *     pending_payouts_usd: float,
     *     reserved_insurance_usd: float,
     *     vault_balance_usd: float,
     *     pool_available_usd: float,
     *     category: string,
     *     reasons: list<string>,
     * }
     */
    public function canPay(
        Project $project,
        string $category,
        float $amountUsd,
        ?Vault $vault = null,
        ?int $excludeExpenseId = null,
    ): array {
        return $this->canPayCurrency(
            $project,
            $category,
            DualCurrency::USD,
            $amountUsd,
            $vault,
            $excludeExpenseId,
        );
    }

    /**
     * Dual-currency ability-to-pay (Available Cash per currency — never blended).
     *
     * @return array{
     *     allowed: bool,
     *     currency: string,
     *     amount: float,
     *     amount_usd: float,
     *     amount_iqd: float,
     *     available_usd: float,
     *     available_iqd: float,
     *     pending_usd: float,
     *     pending_iqd: float,
     *     reserved_usd: float,
     *     reserved_iqd: float,
     *     vault_balance_usd: float,
     *     vault_balance_iqd: float,
     *     pool_available_usd: float,
     *     category: string,
     *     reasons: list<string>,
     * }
     */
    public function canPayCurrency(
        Project $project,
        string $category,
        string $currency,
        float $amount,
        ?Vault $vault = null,
        ?int $excludeExpenseId = null,
        ?int $excludePayoutId = null,
    ): array {
        $legs = DualCurrency::legs($currency, $amount);
        $vault ??= $this->zhakoVault();
        $this->poolColumn($category);

        $snap = $this->dualSnapshot($vault, $excludeExpenseId);
        // Pending already includes this draft when exclude ids applied.
        if ($excludePayoutId !== null) {
            $pendingUsd = round(max(0, $snap['pending_usd'] - (float) Payout::query()
                ->where('id', $excludePayoutId)
                ->where('status', Payout::STATUS_PENDING)
                ->value('amount_usd')), 2);
            $pendingIqd = round(max(0, $snap['pending_iqd'] - (float) Payout::query()
                ->where('id', $excludePayoutId)
                ->where('status', Payout::STATUS_PENDING)
                ->value('amount_iqd')), 2);
            $snap['available_usd'] = round(max(0, $snap['balance_usd'] - $pendingUsd - $snap['reserved_usd']), 2);
            $snap['available_iqd'] = round(max(0, $snap['balance_iqd'] - $pendingIqd - $snap['reserved_iqd']), 2);
            $snap['pending_usd'] = $pendingUsd;
            $snap['pending_iqd'] = $pendingIqd;
        }

        $pool = $this->poolAvailableUsd($project, $category);
        $reasons = [];

        if ($legs['currency'] === DualCurrency::USD) {
            if ($legs['amount_usd'] > $snap['available_usd']) {
                $reasons[] = sprintf(
                    'Request %.2f USD exceeds Available Cash %.2f USD (vault %.2f − pending %.2f − reserved insurance %.2f).',
                    $legs['amount_usd'],
                    $snap['available_usd'],
                    $snap['balance_usd'],
                    $snap['pending_usd'],
                    $snap['reserved_usd'],
                );
            }
            if ($legs['amount_usd'] > $pool) {
                $reasons[] = sprintf(
                    'Request %.2f USD exceeds %s pool balance %.2f USD for project #%d.',
                    $legs['amount_usd'],
                    $category,
                    $pool,
                    $project->id,
                );
            }
        } else {
            if ($legs['amount_iqd'] > $snap['available_iqd']) {
                $reasons[] = sprintf(
                    'Request %.2f IQD exceeds Available Cash %.2f IQD (vault %.2f − pending %.2f − reserved insurance %.2f).',
                    $legs['amount_iqd'],
                    $snap['available_iqd'],
                    $snap['balance_iqd'],
                    $snap['pending_iqd'],
                    $snap['reserved_iqd'],
                );
            }
        }

        return [
            'allowed' => $reasons === [],
            'currency' => $legs['currency'],
            'amount' => DualCurrency::primaryAmount($legs),
            'amount_usd' => $legs['amount_usd'],
            'amount_iqd' => $legs['amount_iqd'],
            'available_usd' => $snap['available_usd'],
            'available_iqd' => $snap['available_iqd'],
            'pending_usd' => $snap['pending_usd'],
            'pending_iqd' => $snap['pending_iqd'],
            'reserved_usd' => $snap['reserved_usd'],
            'reserved_iqd' => $snap['reserved_iqd'],
            'pending_payouts_usd' => $snap['pending_usd'],
            'reserved_insurance_usd' => $snap['reserved_usd'],
            'vault_balance_usd' => $snap['balance_usd'],
            'vault_balance_iqd' => $snap['balance_iqd'],
            'pool_available_usd' => $pool,
            'category' => $category,
            'reasons' => $reasons,
        ];
    }

    /**
     * @throws InvalidArgumentException when the payout must be blocked
     * @return array<string, mixed>
     */
    public function assertCanPay(
        Project $project,
        string $category,
        float $amountUsd,
        ?Vault $vault = null,
        ?int $excludeExpenseId = null,
    ): array {
        $result = $this->canPay($project, $category, $amountUsd, $vault, $excludeExpenseId);

        if (! $result['allowed']) {
            throw new InvalidArgumentException(implode(' ', $result['reasons']));
        }

        return $result;
    }

    /**
     * @throws InvalidArgumentException when the payout must be blocked
     * @return array<string, mixed>
     */
    public function assertCanPayCurrency(
        Project $project,
        string $category,
        string $currency,
        float $amount,
        ?Vault $vault = null,
        ?int $excludeExpenseId = null,
        ?int $excludePayoutId = null,
    ): array {
        $result = $this->canPayCurrency(
            $project,
            $category,
            $currency,
            $amount,
            $vault,
            $excludeExpenseId,
            $excludePayoutId,
        );

        if (! $result['allowed']) {
            throw new InvalidArgumentException(implode(' ', $result['reasons']));
        }

        return $result;
    }

    protected function poolColumn(string $category): string
    {
        if (! isset(self::CATEGORY_POOL_COLUMNS[$category])) {
            throw new InvalidArgumentException("Unknown payout category [{$category}].");
        }

        return self::CATEGORY_POOL_COLUMNS[$category];
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
