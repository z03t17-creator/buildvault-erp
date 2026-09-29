<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\RetentionHold;
use App\Models\Vault;
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
            ->where('status', Payout::STATUS_PENDING)
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
            ->where('approval_status', Expense::STATUS_PENDING);

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
            ->where('status', Payout::STATUS_PENDING)
            ->sum('amount_iqd');

        return round((float) $sum, 2);
    }

    public function pendingExpensesIqd(?Vault $vault = null, ?int $excludeExpenseId = null): float
    {
        $vault ??= $this->zhakoVault();

        $query = Expense::query()
            ->where('vault_id', $vault->id)
            ->where('approval_status', Expense::STATUS_PENDING);

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
        if ($amountUsd <= 0) {
            throw new InvalidArgumentException('Request amount must be greater than zero.');
        }

        $amountUsd = round($amountUsd, 2);
        $vault ??= $this->zhakoVault();
        $this->poolColumn($category); // validate category

        $pending = $this->pendingCommitmentsUsd($vault, $excludeExpenseId);
        $reserved = $this->reservedInsuranceUsd($vault);
        $available = $this->availableUsd($vault, $excludeExpenseId);
        $pool = $this->poolAvailableUsd($project, $category);

        $reasons = [];

        if ($amountUsd > $available) {
            $reasons[] = sprintf(
                'Request %.2f USD exceeds available liquidity %.2f USD (vault %.2f − pending %.2f − reserved insurance %.2f).',
                $amountUsd,
                $available,
                (float) $vault->balance_usd,
                $pending,
                $reserved,
            );
        }

        if ($amountUsd > $pool) {
            $reasons[] = sprintf(
                'Request %.2f USD exceeds %s pool balance %.2f USD for project #%d.',
                $amountUsd,
                $category,
                $pool,
                $project->id,
            );
        }

        return [
            'allowed' => $reasons === [],
            'amount_usd' => $amountUsd,
            'available_usd' => $available,
            'pending_payouts_usd' => $pending,
            'reserved_insurance_usd' => $reserved,
            'vault_balance_usd' => round((float) $vault->balance_usd, 2),
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
