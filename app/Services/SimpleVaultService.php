<?php

namespace App\Services;

use App\Models\Penalty;
use App\Models\Staff;
use App\Models\StaffRate;
use App\Models\Vault;
use App\Models\VaultLine;
use App\Models\VaultLineItem;
use App\Support\DualCurrency;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Simple vault ledger — advance / salary / expense / daily_pay / unit_pay / job_pay.
 * USD and IQD never blend on one line.
 */
class SimpleVaultService
{
    public const HOLD_RATIO = 0.10;

    public const HOLD_DAYS = VaultLine::HOLD_DAYS;

    public function zhakoVault(): Vault
    {
        return Vault::query()->firstOrCreate(
            ['name' => VaultSeeder::NAME],
            ['balance_usd' => 0, 'balance_iqd' => 0],
        );
    }

    /**
     * Project سلفە available cash in one currency (advances in − expenses out).
     * Holds still locked on advances are excluded from available.
     */
    public function projectAvailableCash(
        int $projectId,
        string $currency,
        CarbonInterface|string|null $asOf = null,
    ): float {
        $currency = $this->currency($currency);
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();

        $lines = VaultLine::query()
            ->where('project_id', $projectId)
            ->ofCurrency($currency)
            ->whereDate('occurred_on', '<=', $asOf->toDateString())
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $available = 0.0;
        foreach ($lines as $line) {
            $amount = round((float) $line->amount, 2);
            $hold = round((float) $line->hold_amount, 2);

            if ($line->kind === VaultLine::KIND_ADVANCE) {
                $available = round($available + ($amount - $hold), 2);
                if ($hold > 0 && ($line->companyHoldUnlocked($asOf) || $line->hold_released_at !== null)) {
                    $available = round($available + $hold, 2);
                }
                continue;
            }

            if ($line->kind === VaultLine::KIND_EXPENSE) {
                $available = round($available - $amount, 2);
            }
        }

        return round($available, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function postAdvance(array $data): VaultLine
    {
        $amount = $this->positiveAmount($data['amount'] ?? null);
        $currency = $this->currency($data['currency'] ?? null);
        $occurredOn = $this->date($data['occurred_on'] ?? null);
        $hold = round($amount * self::HOLD_RATIO, 2);

        return $this->storeLine([
            'vault_id' => $data['vault_id'] ?? $this->zhakoVault()->id,
            'kind' => VaultLine::KIND_ADVANCE,
            'occurred_on' => $occurredOn->toDateString(),
            'amount' => $amount,
            'currency' => $currency,
            'project_id' => $data['project_id'] ?? null,
            'staff_id' => $data['staff_id'] ?? null,
            'note' => $data['note'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            ...$this->locationAttrs($data),
            'hold_amount' => $hold,
            'hold_pool' => VaultLine::HOLD_POOL_COMPANY_INSURANCE,
            'unlock_date' => $occurredOn->copy()->addDays(self::HOLD_DAYS)->toDateString(),
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Monthly salary (penalties reduce). 10% toggle defaults off.
     *
     * @param  array<string, mixed>  $data
     */
    public function postSalary(array $data): VaultLine
    {
        $staff = Staff::query()->findOrFail($data['staff_id']);
        if (! $staff->isMonthly()) {
            throw new InvalidArgumentException('Salary lines require monthly staff.');
        }

        $month = isset($data['month'])
            ? Carbon::parse($data['month'])->startOfMonth()
            : $this->date($data['occurred_on'] ?? null)->startOfMonth();

        $amount = array_key_exists('amount', $data) && $data['amount'] !== null && $data['amount'] !== ''
            ? $this->nonNegativeAmount($data['amount'])
            : $this->salaryDueFor($staff, $month);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Salary amount after penalties must be greater than zero.');
        }

        $occurredOn = isset($data['occurred_on'])
            ? $this->date($data['occurred_on'])
            : $month->copy()->endOfMonth();

        $applyInsurance = array_key_exists('apply_insurance', $data)
            ? filter_var($data['apply_insurance'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : false;
        if ($applyInsurance === null) {
            $applyInsurance = false;
        }

        $hold = $applyInsurance ? round($amount * self::HOLD_RATIO, 2) : 0.0;

        return $this->storeLine([
            'vault_id' => $data['vault_id'] ?? $this->zhakoVault()->id,
            'kind' => VaultLine::KIND_SALARY,
            'occurred_on' => $occurredOn->toDateString(),
            'amount' => $amount,
            'currency' => strtoupper((string) $staff->currency),
            'project_id' => $data['project_id'] ?? null,
            'staff_id' => $staff->id,
            'note' => $data['note'] ?? sprintf('Salary %s', $month->format('Y-m')),
            'purpose' => $data['purpose'] ?? null,
            ...$this->locationAttrs($data),
            'hold_amount' => $hold,
            'hold_pool' => $applyInsurance ? VaultLine::HOLD_POOL_STAFF_OWED : null,
            'unlock_date' => $applyInsurance
                ? $occurredOn->copy()->addDays(self::HOLD_DAYS)->toDateString()
                : null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function postExpense(array $data): VaultLine
    {
        $amount = $this->positiveAmount($data['amount'] ?? null);
        $currency = $this->currency($data['currency'] ?? null);

        return $this->storeLine([
            'vault_id' => $data['vault_id'] ?? $this->zhakoVault()->id,
            'kind' => VaultLine::KIND_EXPENSE,
            'occurred_on' => $this->date($data['occurred_on'] ?? null)->toDateString(),
            'amount' => $amount,
            'currency' => $currency,
            'project_id' => $data['project_id'] ?? null,
            'staff_id' => $data['staff_id'] ?? null,
            'note' => $data['note'] ?? null,
            'expense_type' => $data['expense_type'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            ...$this->locationAttrs($data),
            'hold_amount' => 0,
            'hold_pool' => null,
            'unlock_date' => null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Legacy job_pay for time/unit (compat). Prefer postDailyPay / postUnitPayWithItems.
     *
     * @param  array<string, mixed>  $data
     */
    public function postJobPay(array $data): VaultLine
    {
        $staff = Staff::query()->findOrFail($data['staff_id']);
        if (! $staff->isDaily() && ! $staff->isUnit()) {
            throw new InvalidArgumentException('Job pay lines require daily or unit staff.');
        }

        $amount = $this->positiveAmount($data['amount'] ?? null);
        $currency = $this->currency($data['currency'] ?? ($staff->currency ?: 'IQD'));
        $occurredOn = $this->date($data['occurred_on'] ?? null);
        $applyInsurance = $this->boolDefault($data['apply_insurance'] ?? null, true);
        $hold = $applyInsurance ? round($amount * self::HOLD_RATIO, 2) : 0.0;

        return $this->storeLine([
            'vault_id' => $data['vault_id'] ?? $this->zhakoVault()->id,
            'kind' => VaultLine::KIND_JOB_PAY,
            'occurred_on' => $occurredOn->toDateString(),
            'amount' => $amount,
            'currency' => $currency,
            'project_id' => $data['project_id'] ?? null,
            'staff_id' => $staff->id,
            'note' => $data['note'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            ...$this->locationAttrs($data),
            'hold_amount' => $hold,
            'hold_pool' => $applyInsurance ? VaultLine::HOLD_POOL_STAFF_OWED : null,
            'unlock_date' => $applyInsurance
                ? $occurredOn->copy()->addDays(self::HOLD_DAYS)->toDateString()
                : null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Daily pay: days × day_rate + optional transport. 10% default on labor only.
     *
     * @param  array<string, mixed>  $data
     */
    public function postDailyPay(array $data): VaultLine
    {
        $staff = Staff::query()->findOrFail($data['staff_id']);
        if (! $staff->isDaily()) {
            throw new InvalidArgumentException('Daily pay requires daily staff.');
        }

        $days = round((float) ($data['days_count'] ?? $data['days'] ?? 0), 2);
        if ($days <= 0) {
            throw new InvalidArgumentException('Days must be greater than zero.');
        }

        $dayRate = array_key_exists('day_rate', $data) && $data['day_rate'] !== null && $data['day_rate'] !== ''
            ? $this->positiveAmount($data['day_rate'])
            : $this->positiveAmount($staff->day_rate);

        $transport = 0.0;
        if (array_key_exists('transport_amount', $data) && $data['transport_amount'] !== null && $data['transport_amount'] !== '') {
            $transport = $this->nonNegativeAmount($data['transport_amount']);
        }

        $labor = round($days * $dayRate, 2);
        $amount = round($labor + $transport, 2);
        $currency = $this->currency($data['currency'] ?? $staff->currency);
        $occurredOn = $this->date($data['occurred_on'] ?? null);
        $applyInsurance = $this->boolDefault($data['apply_insurance'] ?? null, true);
        $hold = $applyInsurance ? round($labor * self::HOLD_RATIO, 2) : 0.0;

        $purpose = $data['purpose'] ?? null;
        if ($purpose === null || $purpose === '') {
            $purpose = trim($days.' ڕۆژ × '.$dayRate.' '.$currency);
            if ($transport > 0) {
                $purpose .= ' + '.$transport.' '.$currency.' گواستنەوە';
            }
        }

        return $this->storeLine([
            'vault_id' => $data['vault_id'] ?? $this->zhakoVault()->id,
            'kind' => VaultLine::KIND_DAILY_PAY,
            'occurred_on' => $occurredOn->toDateString(),
            'amount' => $amount,
            'currency' => $currency,
            'project_id' => $data['project_id'] ?? null,
            'staff_id' => $staff->id,
            'note' => $data['note'] ?? null,
            'purpose' => $purpose,
            ...$this->locationAttrs($data),
            'days_count' => $days,
            'day_rate' => $dayRate,
            'transport_amount' => $transport > 0 ? $transport : null,
            'hold_amount' => $hold,
            'hold_pool' => $applyInsurance ? VaultLine::HOLD_POOL_STAFF_OWED : null,
            'unlock_date' => $applyInsurance
                ? $occurredOn->copy()->addDays(self::HOLD_DAYS)->toDateString()
                : null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Unit pay with line items. 10% default on.
     *
     * @param  array<string, mixed>  $data
     */
    public function postUnitPayWithItems(array $data): VaultLine
    {
        $staff = Staff::query()->findOrFail($data['staff_id']);
        if (! $staff->isUnit()) {
            throw new InvalidArgumentException('Unit pay requires unit staff.');
        }

        $items = $data['items'] ?? [];
        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('Unit pay needs at least one item row.');
        }

        $normalized = [];
        $total = 0.0;
        $currency = null;

        foreach (array_values($items) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $itemName = trim((string) ($row['item_name'] ?? ''));
            $unit = trim((string) ($row['unit'] ?? ''));
            $qty = round((float) ($row['quantity'] ?? 0), 4);
            $rate = round((float) ($row['unit_rate'] ?? 0), 4);
            $rowCurrency = strtoupper((string) ($row['currency'] ?? $data['currency'] ?? ''));

            if ($itemName === '' || $unit === '' || $qty <= 0 || $rate <= 0) {
                throw new InvalidArgumentException('Each unit row needs item, unit, quantity, and rate.');
            }
            if (! in_array($rowCurrency, DualCurrency::CURRENCIES, true)) {
                throw new InvalidArgumentException('Each unit row needs USD or IQD currency.');
            }
            if ($currency === null) {
                $currency = $rowCurrency;
            } elseif ($currency !== $rowCurrency) {
                throw new InvalidArgumentException('All unit rows must use the same currency.');
            }

            $subtotal = round($qty * $rate, 2);
            $total = round($total + $subtotal, 2);
            $normalized[] = [
                'staff_rate_id' => isset($row['staff_rate_id']) && $row['staff_rate_id']
                    ? (int) $row['staff_rate_id']
                    : null,
                'item_name' => $itemName,
                'unit' => $unit,
                'quantity' => $qty,
                'unit_rate' => $rate,
                'subtotal' => $subtotal,
                'sort_order' => $i,
            ];
        }

        if ($normalized === [] || $total <= 0 || $currency === null) {
            throw new InvalidArgumentException('Unit pay total must be greater than zero.');
        }

        // Validate staff_rate_ids belong to this staff when set.
        foreach ($normalized as $row) {
            if ($row['staff_rate_id'] === null) {
                continue;
            }
            $ok = StaffRate::query()
                ->where('id', $row['staff_rate_id'])
                ->where('staff_id', $staff->id)
                ->exists();
            if (! $ok) {
                throw new InvalidArgumentException('Rate row does not belong to this staff.');
            }
        }

        $occurredOn = $this->date($data['occurred_on'] ?? null);
        $applyInsurance = $this->boolDefault($data['apply_insurance'] ?? null, true);
        $hold = $applyInsurance ? round($total * self::HOLD_RATIO, 2) : 0.0;

        return DB::transaction(function () use ($data, $staff, $normalized, $total, $currency, $occurredOn, $applyInsurance, $hold) {
            $line = VaultLine::query()->create([
                'vault_id' => $data['vault_id'] ?? $this->zhakoVault()->id,
                'kind' => VaultLine::KIND_UNIT_PAY,
                'occurred_on' => $occurredOn->toDateString(),
                'amount' => $total,
                'currency' => $currency,
                'project_id' => $data['project_id'] ?? null,
                'staff_id' => $staff->id,
                'note' => $data['note'] ?? null,
                'purpose' => $data['purpose'] ?? (count($normalized).' items'),
                ...$this->locationAttrs($data),
                'hold_amount' => $hold,
                'hold_pool' => $applyInsurance ? VaultLine::HOLD_POOL_STAFF_OWED : null,
                'unlock_date' => $applyInsurance
                    ? $occurredOn->copy()->addDays(self::HOLD_DAYS)->toDateString()
                    : null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($normalized as $row) {
                VaultLineItem::query()->create([
                    'vault_line_id' => $line->id,
                    ...$row,
                ]);
            }

            return $line->fresh(['items']);
        });
    }

    /**
     * Legacy single-quantity unit pay → one item from staff's first rate / legacy fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function postUnitPay(array $data): VaultLine
    {
        $staff = Staff::query()->with('rates')->findOrFail($data['staff_id']);
        if (! $staff->isUnit()) {
            throw new InvalidArgumentException('Unit pay requires unit staff.');
        }

        $quantity = round((float) ($data['quantity'] ?? 0), 4);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $rate = $staff->rates->first();
        $unitRate = $rate ? (float) $rate->rate : (float) $staff->unit_rate;
        $unit = $rate?->unit ?: ($staff->rate_unit ?: 'دانە');
        $item = $rate?->item_name ?: 'کار';
        $currency = $rate?->currency ?: ($staff->currency ?: 'IQD');

        return $this->postUnitPayWithItems([
            ...$data,
            'items' => [[
                'staff_rate_id' => $rate?->id,
                'item_name' => $item,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_rate' => $unitRate,
                'currency' => $currency,
            ]],
        ]);
    }

    public function salaryDueFor(Staff $staff, CarbonInterface|string $month): float
    {
        if (! $staff->isMonthly()) {
            return 0.0;
        }

        $month = Carbon::parse($month)->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $monthly = round((float) $staff->monthly_salary, 2);
        $days = max(1, $month->daysInMonth);
        $daily = round($monthly / $days, 2);

        $penalties = Penalty::query()
            ->where('staff_id', $staff->id)
            ->whereDate('occurred_on', '>=', $month->toDateString())
            ->whereDate('occurred_on', '<=', $end->toDateString())
            ->whereIn('status', [Penalty::STATUS_PENDING, Penalty::STATUS_APPLIED])
            ->get();

        $cut = 0.0;
        foreach ($penalties as $penalty) {
            if ($penalty->type === Penalty::TYPE_FORFEIT_DAY) {
                $cut = round($cut + $daily, 2);
                continue;
            }

            $currency = strtoupper((string) ($penalty->currency ?: $staff->currency));
            if ($currency === DualCurrency::USD) {
                $cut = round($cut + (float) $penalty->amount_usd, 2);
            } else {
                $cut = round($cut + (float) $penalty->amount_iqd, 2);
            }
        }

        return max(0.0, round($monthly - $cut, 2));
    }

    /**
     * @return array<string, mixed>
     */
    public function estimate(
        string $currency,
        CarbonInterface|string|null $asOf = null,
        ?float $salariesToPay = null,
        ?float $expensesToPay = null,
        ?Vault $vault = null,
    ): array {
        $currency = $this->currency($currency);
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();
        $vault ??= $this->zhakoVault();

        $balances = $this->balances($currency, $asOf, $vault);

        if ($salariesToPay === null) {
            $salariesToPay = $this->openSalariesDue($currency, $asOf);
        }
        if ($expensesToPay === null) {
            $expensesToPay = 0.0;
        }

        $salariesToPay = round(max(0, $salariesToPay), 2);
        $expensesToPay = round(max(0, $expensesToPay), 2);
        $obligations = round($salariesToPay + $expensesToPay, 2);
        $available = $balances['available_cash'];
        $shortfall = round(max(0, $obligations - $available), 2);

        return [
            'currency' => $currency,
            'as_of' => $asOf->toDateString(),
            'available_cash' => $available,
            'company_insurance_held' => $balances['company_insurance_held'],
            'staff_owed_held' => $balances['staff_owed_held'],
            'salaries_to_pay' => $salariesToPay,
            'expenses_to_pay' => $expensesToPay,
            'obligations' => $obligations,
            'covers' => $shortfall <= 0.0,
            'shortfall' => $shortfall,
        ];
    }

    /**
     * @return array{available_cash: float, company_insurance_held: float, staff_owed_held: float}
     */
    public function balances(
        string $currency,
        CarbonInterface|string|null $asOf = null,
        ?Vault $vault = null,
    ): array {
        $currency = $this->currency($currency);
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();
        $vault ??= $this->zhakoVault();

        $lines = VaultLine::query()
            ->where('vault_id', $vault->id)
            ->ofCurrency($currency)
            ->whereDate('occurred_on', '<=', $asOf->toDateString())
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $available = 0.0;
        $companyHold = 0.0;
        $staffHold = 0.0;

        foreach ($lines as $line) {
            $amount = round((float) $line->amount, 2);
            $hold = round((float) $line->hold_amount, 2);

            if ($line->kind === VaultLine::KIND_ADVANCE) {
                $available = round($available + ($amount - $hold), 2);
                if ($hold > 0) {
                    if ($line->companyHoldUnlocked($asOf) || $line->hold_released_at !== null) {
                        $available = round($available + $hold, 2);
                    } else {
                        $companyHold = round($companyHold + $hold, 2);
                    }
                }
                continue;
            }

            if ($line->kind === VaultLine::KIND_EXPENSE) {
                $available = round($available - $amount, 2);
                continue;
            }

            if ($line->kind === VaultLine::KIND_SALARY && $line->hold_pool !== VaultLine::HOLD_POOL_STAFF_OWED) {
                $available = round($available - $amount, 2);
                continue;
            }

            // salary with staff hold, job_pay, daily_pay, unit_pay
            if (in_array($line->kind, [
                VaultLine::KIND_JOB_PAY,
                VaultLine::KIND_DAILY_PAY,
                VaultLine::KIND_UNIT_PAY,
                VaultLine::KIND_SALARY,
            ], true)) {
                $available = round($available - ($amount - $hold), 2);
                if ($hold > 0 && $line->hold_released_at === null) {
                    $staffHold = round($staffHold + $hold, 2);
                }
            }
        }

        return [
            'available_cash' => $available,
            'company_insurance_held' => $companyHold,
            'staff_owed_held' => $staffHold,
        ];
    }

    public function releaseStaffHold(VaultLine $line, CarbonInterface|string|null $asOf = null): VaultLine
    {
        $allowed = in_array($line->kind, [
            VaultLine::KIND_JOB_PAY,
            VaultLine::KIND_DAILY_PAY,
            VaultLine::KIND_UNIT_PAY,
            VaultLine::KIND_SALARY,
        ], true);

        if (! $allowed || $line->hold_pool !== VaultLine::HOLD_POOL_STAFF_OWED) {
            throw new InvalidArgumentException('Only staff-owed holds can be released this way.');
        }

        if ((float) $line->hold_amount <= 0) {
            throw new InvalidArgumentException('This line has no open hold.');
        }

        if ($line->hold_released_at !== null) {
            throw new InvalidArgumentException('This staff hold is already confirmed.');
        }

        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();
        $line->hold_released_at = $asOf->toDateTimeString();
        $line->save();

        return $line->fresh();
    }

    /**
     * Soft-delete a staff payment so it leaves the cash and hold totals.
     */
    public function voidStaffPay(VaultLine $line): void
    {
        if (! in_array($line->kind, VaultLine::STAFF_HOLD_KINDS, true)) {
            throw new InvalidArgumentException('Only staff pay lines can be removed.');
        }

        $line->delete();
    }

    public function openSalariesDue(string $currency, CarbonInterface|string|null $asOf = null): float
    {
        $currency = $this->currency($currency);
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();
        $month = $asOf->copy()->startOfMonth();

        $staff = Staff::query()
            ->where(function ($q) {
                $q->where('pay_model', Staff::PAY_MONTHLY)
                    ->orWhere('kind', Staff::KIND_SALARY);
            })
            ->where('currency', $currency)
            ->get();

        $due = 0.0;
        foreach ($staff as $person) {
            $net = $this->salaryDueFor($person, $month);
            $paid = (float) VaultLine::query()
                ->where('kind', VaultLine::KIND_SALARY)
                ->where('staff_id', $person->id)
                ->where('currency', $currency)
                ->whereDate('occurred_on', '>=', $month->toDateString())
                ->whereDate('occurred_on', '<=', $month->copy()->endOfMonth()->toDateString())
                ->sum('amount');
            $due = round($due + max(0, $net - $paid), 2);
        }

        return $due;
    }

    public function openExpensesDue(string $currency): float
    {
        $currency = $this->currency($currency);

        if (! class_exists(\App\Models\Expense::class)) {
            return 0.0;
        }

        $column = $currency === DualCurrency::USD ? 'amount_usd' : 'amount_iqd';

        return round((float) \App\Models\Expense::query()
            ->whereIn('approval_status', [
                \App\Models\Expense::STATUS_PENDING,
                \App\Models\Expense::STATUS_HELD,
            ])
            ->where($column, '>', 0)
            ->sum($column), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companyInsuranceUnlocks(
        ?Vault $vault = null,
        CarbonInterface|string|null $asOf = null,
    ): array {
        $vault ??= $this->zhakoVault();
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();

        return VaultLine::query()
            ->where('vault_id', $vault->id)
            ->where('kind', VaultLine::KIND_ADVANCE)
            ->where('hold_pool', VaultLine::HOLD_POOL_COMPANY_INSURANCE)
            ->where('hold_amount', '>', 0)
            ->whereNull('hold_released_at')
            ->orderBy('unlock_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (VaultLine $line) => ! $line->companyHoldUnlocked($asOf))
            ->map(fn (VaultLine $line) => [
                'id' => $line->id,
                'currency' => $line->currency,
                'amount' => round((float) $line->hold_amount, 2),
                'unlock_date' => $line->unlock_date?->toDateString(),
                'occurred_on' => $line->occurred_on?->toDateString(),
                'project_id' => $line->project_id,
                'note' => $line->note,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staffHoldDues(
        ?Vault $vault = null,
        CarbonInterface|string|null $asOf = null,
    ): array {
        $vault ??= $this->zhakoVault();
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();

        return VaultLine::query()
            ->with('staff:id,name')
            ->where('vault_id', $vault->id)
            ->whereIn('kind', [
                VaultLine::KIND_JOB_PAY,
                VaultLine::KIND_DAILY_PAY,
                VaultLine::KIND_UNIT_PAY,
                VaultLine::KIND_SALARY,
            ])
            ->where('hold_pool', VaultLine::HOLD_POOL_STAFF_OWED)
            ->where('hold_amount', '>', 0)
            ->whereNull('hold_released_at')
            ->orderBy('unlock_date')
            ->orderBy('id')
            ->get()
            ->map(fn (VaultLine $line) => [
                'id' => $line->id,
                'currency' => $line->currency,
                'amount' => round((float) $line->hold_amount, 2),
                'unlock_date' => $line->unlock_date?->toDateString(),
                'occurred_on' => $line->occurred_on?->toDateString(),
                'staff_id' => $line->staff_id,
                'staff_name' => $line->staff?->name,
                'note' => $line->note,
                'due' => $line->unlock_date
                    ? $line->unlock_date->copy()->startOfDay()->lessThanOrEqualTo($asOf)
                    : false,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSnapshot(
        ?Vault $vault = null,
        CarbonInterface|string|null $asOf = null,
    ): array {
        $vault ??= $this->zhakoVault();
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();

        $estimates = [];
        $available = [];
        foreach (DualCurrency::CURRENCIES as $currency) {
            $expenses = $this->openExpensesDue($currency);
            $estimate = $this->estimate($currency, $asOf, null, $expenses, $vault);
            $estimates[$currency] = $estimate;
            $available[$currency] = $estimate['available_cash'];
        }

        return [
            'as_of' => $asOf->toDateString(),
            'available_cash' => [
                'USD' => $available[DualCurrency::USD],
                'IQD' => $available[DualCurrency::IQD],
            ],
            'insurance_unlocks' => $this->companyInsuranceUnlocks($vault, $asOf),
            'staff_holds' => $this->staffHoldDues($vault, $asOf),
            'estimates' => [
                'USD' => $estimates[DualCurrency::USD],
                'IQD' => $estimates[DualCurrency::IQD],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function locationAttrs(array $data): array
    {
        $site = isset($data['site_kind']) ? trim((string) $data['site_kind']) : null;
        if ($site === '') {
            $site = null;
        }
        if ($site !== null && ! in_array($site, VaultLine::SITE_KINDS, true)) {
            throw new InvalidArgumentException('site_kind must be villa or building.');
        }

        return [
            'site_kind' => $site,
            'block' => $this->nullableString($data['block'] ?? null),
            'zone' => $this->nullableString($data['zone'] ?? null),
            'floor' => $this->nullableString($data['floor'] ?? null),
            'apartment_number' => $this->nullableString($data['apartment_number'] ?? null),
            'apartment_model' => $this->nullableString($data['apartment_model'] ?? null),
            'villa_number' => $this->nullableString($data['villa_number'] ?? null),
            'area' => $this->nullableString($data['area'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function boolDefault(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed === null ? $default : $parsed;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function storeLine(array $attrs): VaultLine
    {
        return DB::transaction(function () use ($attrs) {
            return VaultLine::query()->create($attrs)->fresh();
        });
    }

    private function currency(mixed $currency): string
    {
        $currency = strtoupper(trim((string) $currency));
        if (! in_array($currency, DualCurrency::CURRENCIES, true)) {
            throw new InvalidArgumentException('Currency must be USD or IQD.');
        }

        return $currency;
    }

    private function positiveAmount(mixed $amount): float
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $amount;
    }

    private function nonNegativeAmount(mixed $amount): float
    {
        $amount = round((float) $amount, 2);
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        return $amount;
    }

    private function date(mixed $value): Carbon
    {
        return $value ? Carbon::parse($value)->startOfDay() : now()->startOfDay();
    }
}
