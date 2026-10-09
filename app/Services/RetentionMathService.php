<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Dual 10%/180-day retention math stubs (client→company and company→staff).
 *
 * Phase 1: pure calculation helpers — no CRUD UI. Settlement engines in later
 * phases call these. Currencies are never blended.
 */
class RetentionMathService
{
    public const DEFAULT_HOLD_PCT = 10.0;

    public const DEFAULT_MATURITY_DAYS = 180;

    /**
     * @return array{
     *     gross_usd: float,
     *     gross_iqd: float,
     *     hold_pct: float,
     *     retention_usd: float,
     *     retention_iqd: float,
     *     net_after_retention_usd: float,
     *     net_after_retention_iqd: float,
     *     hold_start: string,
     *     maturity_date: string,
     *     maturity_days: int,
     * }
     */
    public function clientAdvanceRetention(
        float $grossUsd,
        float $grossIqd,
        Carbon|string|null $holdStart = null,
        ?float $holdPct = null,
        ?int $maturityDays = null,
    ): array {
        return $this->layer('client', $grossUsd, $grossIqd, $holdStart, $holdPct, $maturityDays);
    }

    /**
     * Staff unit work-pay retention (NOT employee monthly salary).
     *
     * @return array{
     *     gross_usd: float,
     *     gross_iqd: float,
     *     hold_pct: float,
     *     retention_usd: float,
     *     retention_iqd: float,
     *     net_after_retention_usd: float,
     *     net_after_retention_iqd: float,
     *     hold_start: string,
     *     maturity_date: string,
     *     maturity_days: int,
     * }
     */
    public function staffWorkPayRetention(
        float $grossUsd,
        float $grossIqd,
        Carbon|string|null $holdStart = null,
        ?float $holdPct = null,
        ?int $maturityDays = null,
    ): array {
        return $this->layer('staff', $grossUsd, $grossIqd, $holdStart, $holdPct, $maturityDays);
    }

    /**
     * Settlement stub: Gross − Retention − Advances − Penalties = Net (per currency).
     *
     * @return array{
     *     gross_usd: float,
     *     gross_iqd: float,
     *     retention_usd: float,
     *     retention_iqd: float,
     *     advances_usd: float,
     *     advances_iqd: float,
     *     penalties_usd: float,
     *     penalties_iqd: float,
     *     net_usd: float,
     *     net_iqd: float,
     * }
     */
    public function staffNetPayable(
        float $grossUsd,
        float $grossIqd,
        float $retentionUsd = 0,
        float $retentionIqd = 0,
        float $advancesUsd = 0,
        float $advancesIqd = 0,
        float $penaltiesUsd = 0,
        float $penaltiesIqd = 0,
    ): array {
        $grossUsd = round($grossUsd, 2);
        $grossIqd = round($grossIqd, 2);
        $retentionUsd = round($retentionUsd, 2);
        $retentionIqd = round($retentionIqd, 2);
        $advancesUsd = round($advancesUsd, 2);
        $advancesIqd = round($advancesIqd, 2);
        $penaltiesUsd = round($penaltiesUsd, 2);
        $penaltiesIqd = round($penaltiesIqd, 2);

        return [
            'gross_usd' => $grossUsd,
            'gross_iqd' => $grossIqd,
            'retention_usd' => $retentionUsd,
            'retention_iqd' => $retentionIqd,
            'advances_usd' => $advancesUsd,
            'advances_iqd' => $advancesIqd,
            'penalties_usd' => $penaltiesUsd,
            'penalties_iqd' => $penaltiesIqd,
            'net_usd' => round($grossUsd - $retentionUsd - $advancesUsd - $penaltiesUsd, 2),
            'net_iqd' => round($grossIqd - $retentionIqd - $advancesIqd - $penaltiesIqd, 2),
        ];
    }

    /**
     * Employee monthly salary has NO automatic 10% retention.
     */
    public function employeeSalaryHasRetention(): bool
    {
        return false;
    }

    public function maturityDate(Carbon|string $holdStart, ?int $maturityDays = null): Carbon
    {
        $days = $maturityDays ?? self::DEFAULT_MATURITY_DAYS;
        if ($days < 1) {
            throw new InvalidArgumentException('Maturity days must be >= 1.');
        }

        return Carbon::parse($holdStart)->startOfDay()->addDays($days);
    }

    /**
     * @return array{
     *     gross_usd: float,
     *     gross_iqd: float,
     *     hold_pct: float,
     *     retention_usd: float,
     *     retention_iqd: float,
     *     net_after_retention_usd: float,
     *     net_after_retention_iqd: float,
     *     hold_start: string,
     *     maturity_date: string,
     *     maturity_days: int,
     *     layer: string,
     * }
     */
    protected function layer(
        string $layer,
        float $grossUsd,
        float $grossIqd,
        Carbon|string|null $holdStart,
        ?float $holdPct,
        ?int $maturityDays,
    ): array {
        $pct = $holdPct ?? self::DEFAULT_HOLD_PCT;
        if ($pct < 0 || $pct > 100) {
            throw new InvalidArgumentException('Hold percent must be between 0 and 100.');
        }

        $days = $maturityDays ?? self::DEFAULT_MATURITY_DAYS;
        $start = Carbon::parse($holdStart ?? now())->startOfDay();
        $grossUsd = round(max(0, $grossUsd), 2);
        $grossIqd = round(max(0, $grossIqd), 2);

        $retentionUsd = round($grossUsd * ($pct / 100), 2);
        $retentionIqd = round($grossIqd * ($pct / 100), 2);

        return [
            'layer' => $layer,
            'gross_usd' => $grossUsd,
            'gross_iqd' => $grossIqd,
            'hold_pct' => round($pct, 2),
            'retention_usd' => $retentionUsd,
            'retention_iqd' => $retentionIqd,
            'net_after_retention_usd' => round($grossUsd - $retentionUsd, 2),
            'net_after_retention_iqd' => round($grossIqd - $retentionIqd, 2),
            'hold_start' => $start->toDateString(),
            'maturity_date' => $this->maturityDate($start, $days)->toDateString(),
            'maturity_days' => $days,
        ];
    }
}
