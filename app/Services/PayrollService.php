<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\Penalty;
use App\Models\Worker;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;

class PayrollService
{
    /**
     * Calculate net pay for a worker over an inclusive date range.
     *
     * Attendance is not used. Period base salary comes from the worker's
     * stored rate (`daily_rate_usd`, treated as the month/period base).
     * Optional manual OT hours (argument or `worker.manual_ot_hours`) × OT rate.
     *
     * Net = Base + OT pay
     *     − recorded penalties in period (pending/applied)
     *     − open payroll advances (IQD → USD via FX)
     *     − insurance holdback (% of gross)
     *
     * @return array{
     *     worker_id: int,
     *     from: string,
     *     to: string,
     *     overtime_hours: float,
     *     daily_rate_usd: float,
     *     overtime_rate_usd: float,
     *     base_pay_usd: float,
     *     overtime_pay_usd: float,
     *     gross_pay_usd: float,
     *     recorded_penalties_usd: float,
     *     recorded_penalties_iqd: float,
     *     penalties_usd: float,
     *     advances_iqd: float,
     *     advances_usd: float,
     *     insurance_holdback_pct: float,
     *     insurance_holdback_usd: float,
     *     net_pay_usd: float,
     * }
     */
    public function calculate(
        Worker $worker,
        CarbonInterface|string $from,
        CarbonInterface|string $to,
        ?float $manualOvertimeHours = null,
    ): array {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        $dailyRate = (float) $worker->daily_rate_usd;
        $monthlySalary = (float) ($worker->monthly_salary_usd ?? 0);
        $otRate = (float) $worker->overtime_rate_usd;
        $overtimeHours = $manualOvertimeHours !== null
            ? max(0.0, $manualOvertimeHours)
            : max(0.0, (float) ($worker->manual_ot_hours ?? 0));

        // Period base: monthly salary for Workers, else legacy daily_rate_usd.
        // Not derived from check-in days.
        $basePay = round($monthlySalary > 0 ? $monthlySalary : $dailyRate, 2);
        $otPay = round($overtimeHours * $otRate, 2);
        $gross = round($basePay + $otPay, 2);

        $recorded = $this->recordedPenaltiesInPeriod($worker, $from, $to);
        $recordedUsd = $recorded['usd'];
        $recordedIqd = $recorded['iqd'];
        $penaltiesTotal = round($recordedUsd, 2);

        $advancesIqd = $this->openPayrollAdvancesIqd($worker);
        $fx = $this->usdToIqdRate();
        $advancesUsd = $fx > 0 ? round($advancesIqd / $fx, 2) : 0.0;

        $holdbackPct = $this->insuranceHoldbackPercent();
        $insuranceHoldback = round($gross * ($holdbackPct / 100.0), 2);

        $net = round($gross - $penaltiesTotal - $insuranceHoldback - $advancesUsd, 2);

        return [
            'worker_id' => $worker->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'overtime_hours' => round($overtimeHours, 2),
            'daily_rate_usd' => $dailyRate,
            'overtime_rate_usd' => $otRate,
            'base_pay_usd' => $basePay,
            'overtime_pay_usd' => $otPay,
            'gross_pay_usd' => $gross,
            'recorded_penalties_usd' => $recordedUsd,
            'recorded_penalties_iqd' => $recordedIqd,
            'penalties_usd' => $penaltiesTotal,
            'advances_iqd' => $advancesIqd,
            'advances_usd' => $advancesUsd,
            'insurance_holdback_pct' => $holdbackPct,
            'insurance_holdback_usd' => $insuranceHoldback,
            'net_pay_usd' => $net,
        ];
    }

    /**
     * Pending + applied penalties dated in the pay period (waived excluded).
     *
     * @return array{usd: float, iqd: float}
     */
    protected function recordedPenaltiesInPeriod(
        Worker $worker,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        if (! $worker->exists) {
            return ['usd' => 0.0, 'iqd' => 0.0];
        }

        $fx = $this->usdToIqdRate();

        $rows = Penalty::query()
            ->where('worker_id', $worker->id)
            ->whereIn('status', [Penalty::STATUS_PENDING, Penalty::STATUS_APPLIED])
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('occurred_on')
                        ->whereDate('occurred_on', '>=', $from->toDateString())
                        ->whereDate('occurred_on', '<=', $to->toDateString());
                })->orWhere(function ($inner) use ($from, $to) {
                    // Legacy rows without occurred_on: use created_at date.
                    $inner->whereNull('occurred_on')
                        ->whereDate('created_at', '>=', $from->toDateString())
                        ->whereDate('created_at', '<=', $to->toDateString());
                });
            })
            ->get(['amount_usd', 'amount_iqd']);

        $usd = round((float) $rows->sum('amount_usd'), 2);
        $iqd = round((float) $rows->sum(function (Penalty $p) use ($fx) {
            if ($p->amount_iqd !== null) {
                return (float) $p->amount_iqd;
            }

            return round((float) $p->amount_usd * $fx, 2);
        }), 2);

        return ['usd' => $usd, 'iqd' => $iqd];
    }

    protected function openPayrollAdvancesIqd(Worker $worker): float
    {
        if (! $worker->exists) {
            return 0.0;
        }

        return round((float) EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->where('repayment_method', EmployeeAdvance::REPAY_PAYROLL)
            ->sum('remaining_iqd'), 2);
    }

    protected function insuranceHoldbackPercent(): float
    {
        try {
            return App::make(InsuranceSettings::class)->holdbackPercent();
        } catch (\Throwable) {
            return InsuranceSettings::DEFAULT_HOLDBACK_PCT;
        }
    }

    protected function usdToIqdRate(): float
    {
        try {
            return App::make(ExchangeRateService::class)->getUsdToIqd();
        } catch (\Throwable) {
            return ExchangeRateService::FALLBACK_RATE;
        }
    }
}
