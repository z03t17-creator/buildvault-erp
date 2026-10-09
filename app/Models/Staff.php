<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * Site person for the simple vault.
 * pay_model=monthly → salary + attendance penalties
 * pay_model=daily → day_rate × days (staff-owed hold)
 * pay_model=unit → staff_rates price list × quantities
 *
 * Legacy `kind` (salary|time|unit) stays synced for older queries.
 */
class Staff extends Model
{
    use SoftDeletes;

    public const PAY_MONTHLY = 'monthly';

    public const PAY_DAILY = 'daily';

    public const PAY_UNIT = 'unit';

    /** @var list<string> */
    public const PAY_MODELS = [
        self::PAY_MONTHLY,
        self::PAY_DAILY,
        self::PAY_UNIT,
    ];

    /** @deprecated use PAY_* */
    public const KIND_SALARY = 'salary';

    /** @deprecated use PAY_DAILY */
    public const KIND_TIME = 'time';

    /** @deprecated use PAY_UNIT */
    public const KIND_UNIT = 'unit';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_SALARY,
        self::KIND_TIME,
        self::KIND_UNIT,
    ];

    /** @var list<string> */
    public const DEFAULT_ROLES = [
        'دەرگاچیی',
        'بۆیاخکار',
        'لۆڵەکێش',
        'ئێلکتریک',
        'سقف',
        'Door carpenter',
        'Painter',
        'Plumber',
    ];

    /** @var list<string> */
    public const DEFAULT_RATE_UNITS = [
        'm²',
        'دانە',
        'ڤێلا',
        'ڕۆژ',
        'مانگ',
    ];

    /** @var list<string> */
    public const DEFAULT_ITEM_NAMES = [
        'دەرگای MDF',
        'دەرگای چوونەژوورەوە',
        'دەرگای شافت',
        'ڕووبەر',
        'm²',
        'دەرگای ناوەوە',
        'MDF door',
        'Entrance',
        'Shaft door',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'role',
        'pay_model',
        'kind',
        'trade',
        'monthly_salary',
        'day_rate',
        'currency',
        'unit_rate',
        'rate_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'day_rate' => 'decimal:2',
            'unit_rate' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Staff $staff): void {
            $model = $staff->resolvePayModel();
            if (! in_array($model, self::PAY_MODELS, true)) {
                throw new InvalidArgumentException('Staff pay_model must be monthly, daily, or unit.');
            }

            $staff->pay_model = $model;
            $staff->kind = self::kindFromPayModel($model);
            $staff->role = trim((string) ($staff->role ?: $staff->trade)) ?: null;
            $staff->trade = $staff->role;

            $currency = strtoupper((string) $staff->currency);

            if ($model === self::PAY_MONTHLY) {
                if ($staff->monthly_salary === null || (float) $staff->monthly_salary < 0) {
                    throw new InvalidArgumentException('Monthly staff need a monthly_salary.');
                }
                if (! in_array($currency, ['USD', 'IQD'], true)) {
                    throw new InvalidArgumentException('Monthly staff currency must be USD or IQD.');
                }
                $staff->currency = $currency;
                $staff->day_rate = null;
                $staff->unit_rate = null;
                $staff->rate_unit = null;
            } elseif ($model === self::PAY_DAILY) {
                // day_rate optional on legacy rows; payment form requires it when posting daily_pay.
                if ($staff->day_rate !== null && (float) $staff->day_rate < 0) {
                    throw new InvalidArgumentException('Day rate cannot be negative.');
                }
                if ($currency !== '' && ! in_array($currency, ['USD', 'IQD'], true)) {
                    throw new InvalidArgumentException('Daily staff currency must be USD or IQD.');
                }
                $staff->currency = $currency !== '' ? $currency : null;
                $staff->monthly_salary = null;
                $staff->unit_rate = null;
                $staff->rate_unit = null;
            } else {
                $staff->monthly_salary = null;
                $staff->day_rate = null;
                // currency may come from rates; allow null on staff row
                if ($currency !== '' && ! in_array($currency, ['USD', 'IQD'], true)) {
                    throw new InvalidArgumentException('Unit staff currency must be USD or IQD.');
                }
                $staff->currency = $currency !== '' ? $currency : null;
            }
        });
    }

    public static function kindFromPayModel(string $payModel): string
    {
        return match ($payModel) {
            self::PAY_MONTHLY => self::KIND_SALARY,
            self::PAY_DAILY => self::KIND_TIME,
            default => self::KIND_UNIT,
        };
    }

    public static function payModelFromKind(?string $kind): string
    {
        return match ($kind) {
            self::KIND_SALARY, self::PAY_MONTHLY => self::PAY_MONTHLY,
            self::KIND_TIME, self::PAY_DAILY => self::PAY_DAILY,
            self::KIND_UNIT, self::PAY_UNIT => self::PAY_UNIT,
            default => self::PAY_DAILY,
        };
    }

    public function resolvePayModel(): string
    {
        if ($this->pay_model) {
            return self::payModelFromKind($this->pay_model);
        }

        return self::payModelFromKind($this->kind);
    }

    /**
     * @return list<string>
     */
    public static function suggestedRoles(): array
    {
        return self::uniqueSuggestions(array_merge(
            self::DEFAULT_ROLES,
            self::query()->whereNotNull('role')->where('role', '!=', '')->orderBy('role')->pluck('role')->all(),
            self::query()->whereNotNull('trade')->where('trade', '!=', '')->orderBy('trade')->pluck('trade')->all(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function suggestedRateUnits(): array
    {
        $fromRates = [];
        if (class_exists(StaffRate::class)) {
            try {
                $fromRates = StaffRate::query()
                    ->whereNotNull('unit')
                    ->where('unit', '!=', '')
                    ->orderBy('unit')
                    ->pluck('unit')
                    ->all();
            } catch (\Throwable) {
                $fromRates = [];
            }
        }

        return self::uniqueSuggestions(array_merge(
            self::DEFAULT_RATE_UNITS,
            $fromRates,
            self::query()->whereNotNull('rate_unit')->where('rate_unit', '!=', '')->pluck('rate_unit')->all(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function suggestedItemNames(): array
    {
        $fromRates = [];
        try {
            $fromRates = StaffRate::query()
                ->whereNotNull('item_name')
                ->where('item_name', '!=', '')
                ->orderBy('item_name')
                ->pluck('item_name')
                ->all();
        } catch (\Throwable) {
            $fromRates = [];
        }

        return self::uniqueSuggestions(array_merge(self::DEFAULT_ITEM_NAMES, $fromRates));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function uniqueSuggestions(array $values): array
    {
        $seen = [];
        $out = [];
        foreach ($values as $raw) {
            $unit = trim((string) $raw);
            if ($unit === '') {
                continue;
            }
            $key = mb_strtolower($unit);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $unit;
        }

        return $out;
    }

    public function isMonthly(): bool
    {
        return $this->resolvePayModel() === self::PAY_MONTHLY;
    }

    public function isDaily(): bool
    {
        return $this->resolvePayModel() === self::PAY_DAILY;
    }

    public function isUnit(): bool
    {
        return $this->resolvePayModel() === self::PAY_UNIT;
    }

    /** @deprecated */
    public function isSalary(): bool
    {
        return $this->isMonthly();
    }

    /** @deprecated */
    public function isTime(): bool
    {
        return $this->isDaily();
    }

    public function rates(): HasMany
    {
        return $this->hasMany(StaffRate::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Qty × first rate (or legacy unit_rate). */
    public function amountForQuantity(float $quantity): float
    {
        $quantity = round($quantity, 4);
        if ($quantity <= 0) {
            return 0.0;
        }

        $rate = $this->relationLoaded('rates')
            ? $this->rates->first()
            : $this->rates()->first();

        $unitRate = $rate ? (float) $rate->rate : (float) ($this->unit_rate ?? 0);

        return round($quantity * $unitRate, 2);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function vaultLines(): HasMany
    {
        return $this->hasMany(VaultLine::class);
    }
}
