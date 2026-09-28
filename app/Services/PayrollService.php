<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EmployeeAdvance;
use App\Models\Worker;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class PayrollService
{
    /**
     * Calculate net pay for a worker over an inclusive date range.
     *
     * Net = (Days × Daily Rate) + (OT × OT Rate)
     *     − (Late + Absence penalties)
     *     − open payroll advances (IQD → USD via FX)
     *     − insurance holdback (% of gross)
     *
     * @return array{
     *     worker_id: int,
     *     from: string,
     *     to: string,
     *     days_present: int,
     *     overtime_hours: float,
     *     late_minutes: int,
     *     unexcused_absences: int,
     *     daily_rate_usd: float,
     *     overtime_rate_usd: float,
     *     base_pay_usd: float,
     *     overtime_pay_usd: float,
     *     gross_pay_usd: float,
     *     late_penalty_usd: float,
     *     absence_penalty_usd: float,
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
    ): array {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        /** @var Collection<int, Attendance> $rows */
        $rows = Attendance::query()
            ->where('worker_id', $worker->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get();

        $daysPresent = 0;
        $overtimeHours = 0.0;
        $lateMinutes = 0;
        $unexcusedAbsences = 0;

        foreach ($rows as $row) {
            if ($row->status === Attendance::STATUS_ABSENT_UNEXCUSED) {
                $unexcusedAbsences++;

                continue;
            }

            if ($this->countsAsPaidDay($row)) {
                $daysPresent++;
            }

            if ($this->isWorkedDay($row)) {
                $overtimeHours += (float) $row->overtime_hours;
                $lateMinutes += (int) $row->late_minutes;
            }
        }

        $dailyRate = (float) $worker->daily_rate_usd;
        $otRate = (float) $worker->overtime_rate_usd;

        $basePay = round($daysPresent * $dailyRate, 2);
        $otPay = round($overtimeHours * $otRate, 2);
        $gross = round($basePay + $otPay, 2);
        $latePenalty = round($lateMinutes * $this->latePenaltyPerMinute(), 2);
        $absencePenalty = round($unexcusedAbsences * $this->absencePenaltyAmount($worker), 2);

        $advancesIqd = $this->openPayrollAdvancesIqd($worker);
        $fx = $this->usdToIqdRate();
        $advancesUsd = $fx > 0 ? round($advancesIqd / $fx, 2) : 0.0;

        $holdbackPct = $this->insuranceHoldbackPercent();
        $insuranceHoldback = round($gross * ($holdbackPct / 100.0), 2);

        $net = round($gross - $latePenalty - $absencePenalty - $advancesUsd - $insuranceHoldback, 2);

        return [
            'worker_id' => $worker->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days_present' => $daysPresent,
            'overtime_hours' => round($overtimeHours, 2),
            'late_minutes' => $lateMinutes,
            'unexcused_absences' => $unexcusedAbsences,
            'daily_rate_usd' => $dailyRate,
            'overtime_rate_usd' => $otRate,
            'base_pay_usd' => $basePay,
            'overtime_pay_usd' => $otPay,
            'gross_pay_usd' => $gross,
            'late_penalty_usd' => $latePenalty,
            'absence_penalty_usd' => $absencePenalty,
            'advances_iqd' => $advancesIqd,
            'advances_usd' => $advancesUsd,
            'insurance_holdback_pct' => $holdbackPct,
            'insurance_holdback_usd' => $insuranceHoldback,
            'net_pay_usd' => $net,
        ];
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

    protected function isWorkedDay(Attendance $row): bool
    {
        return in_array($row->status, [
            Attendance::STATUS_PRESENT,
            Attendance::STATUS_LATE,
        ], true);
    }

    protected function countsAsPaidDay(Attendance $row): bool
    {
        if ($this->isWorkedDay($row)) {
            return true;
        }

        if ($row->status === Attendance::STATUS_LEAVE_PAID
            && (bool) config('payroll.count_leave_paid_as_day', true)) {
            return true;
        }

        if ($row->status === Attendance::STATUS_LEAVE_SICK
            && (bool) config('payroll.count_leave_sick_as_day', false)) {
            return true;
        }

        return false;
    }

    protected function latePenaltyPerMinute(): float
    {
        return (float) config('payroll.late_penalty_per_minute_usd', 0.25);
    }

    protected function absencePenaltyAmount(Worker $worker): float
    {
        $explicit = config('payroll.unexcused_absence_penalty_usd');

        if ($explicit !== null && $explicit !== '') {
            return (float) $explicit;
        }

        $multiplier = (float) config('payroll.unexcused_absence_penalty_multiplier', 1.0);

        return round((float) $worker->daily_rate_usd * $multiplier, 2);
    }
}
