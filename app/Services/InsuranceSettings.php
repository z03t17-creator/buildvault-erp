<?php

namespace App\Services;

use App\Models\Setting;
use InvalidArgumentException;

/**
 * Global insurance holdback % and maturity months (defaults: 10% / 6 months).
 */
class InsuranceSettings
{
    public const HOLDBACK_PCT_KEY = 'insurance_holdback_pct';

    public const MATURITY_MONTHS_KEY = 'insurance_maturity_months';

    public const DEFAULT_HOLDBACK_PCT = 10.0;

    public const DEFAULT_MATURITY_MONTHS = 6;

    /**
     * @return array{holdback_pct: float, maturity_months: int}
     */
    public function all(): array
    {
        return [
            'holdback_pct' => $this->holdbackPercent(),
            'maturity_months' => $this->maturityMonths(),
        ];
    }

    public function holdbackPercent(): float
    {
        return Setting::getFloat(self::HOLDBACK_PCT_KEY, self::DEFAULT_HOLDBACK_PCT);
    }

    public function maturityMonths(): int
    {
        $months = Setting::getInt(self::MATURITY_MONTHS_KEY, self::DEFAULT_MATURITY_MONTHS);

        return max(1, $months);
    }

    public function update(float $holdbackPct, int $maturityMonths): void
    {
        if ($holdbackPct < 0 || $holdbackPct > 100) {
            throw new InvalidArgumentException('Insurance holdback percent must be between 0 and 100.');
        }

        if ($maturityMonths < 1 || $maturityMonths > 120) {
            throw new InvalidArgumentException('Insurance maturity months must be between 1 and 120.');
        }

        Setting::putValue(self::HOLDBACK_PCT_KEY, round($holdbackPct, 2));
        Setting::putValue(self::MATURITY_MONTHS_KEY, $maturityMonths);
    }

    /**
     * Seed defaults when missing (idempotent).
     */
    public function ensureDefaults(): void
    {
        if (Setting::query()->where('key', self::HOLDBACK_PCT_KEY)->doesntExist()) {
            Setting::putValue(self::HOLDBACK_PCT_KEY, self::DEFAULT_HOLDBACK_PCT);
        }

        if (Setting::query()->where('key', self::MATURITY_MONTHS_KEY)->doesntExist()) {
            Setting::putValue(self::MATURITY_MONTHS_KEY, self::DEFAULT_MATURITY_MONTHS);
        }
    }
}
