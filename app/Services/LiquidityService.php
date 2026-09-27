<?php

namespace App\Services;

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
     * Available = Vault − (Pending Payouts + Reserved Insurance).
     */
    public function availableUsd(?Vault $vault = null): float
    {
        $vault ??= $this->zhakoVault();

        $pending = $this->pendingPayoutsUsd($vault);
        $reserved = $this->reservedInsuranceUsd($vault);

        return round(max(0, (float) $vault->balance_usd - $pending - $reserved), 2);
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

        return round((float) $sum, 2);
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
    ): array {
        if ($amountUsd <= 0) {
            throw new InvalidArgumentException('Request amount must be greater than zero.');
        }

        $amountUsd = round($amountUsd, 2);
        $vault ??= $this->zhakoVault();
        $this->poolColumn($category); // validate category

        $pending = $this->pendingPayoutsUsd($vault);
        $reserved = $this->reservedInsuranceUsd($vault);
        $available = $this->availableUsd($vault);
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
    ): array {
        $result = $this->canPay($project, $category, $amountUsd, $vault);

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
