<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmployeeAdvanceService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * @param  array{
     *     worker_id: int,
     *     project_id: int,
     *     amount_iqd: float|int|string,
     *     advanced_on: string,
     *     reason: string,
     *     repayment_method: string,
     *     remaining_iqd?: float|int|string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function create(array $data, ?User $actor = null): EmployeeAdvance
    {
        $worker = Worker::query()->findOrFail($data['worker_id']);
        $project = Project::query()->findOrFail($data['project_id']);
        $amount = round((float) $data['amount_iqd'], 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $remaining = array_key_exists('remaining_iqd', $data) && $data['remaining_iqd'] !== null && $data['remaining_iqd'] !== ''
            ? round((float) $data['remaining_iqd'], 2)
            : $amount;

        if ($remaining < 0 || $remaining > $amount) {
            throw new InvalidArgumentException('Remaining amount must be between 0 and the advance amount.');
        }

        $method = (string) $data['repayment_method'];
        if (! in_array($method, EmployeeAdvance::REPAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Invalid repayment method.');
        }

        $status = $remaining <= 0
            ? EmployeeAdvance::STATUS_REPAID
            : EmployeeAdvance::STATUS_OPEN;

        return DB::transaction(function () use ($data, $worker, $project, $amount, $remaining, $method, $status, $actor) {
            $advance = EmployeeAdvance::query()->create([
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'amount_iqd' => $amount,
                'remaining_iqd' => $remaining,
                'advanced_on' => $data['advanced_on'],
                'reason' => $data['reason'],
                'repayment_method' => $method,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'entered_by' => $actor?->id,
            ]);

            // Cash left the vault when the advance was issued.
            $this->postAdvanceOutflow($advance, $amount, $actor?->id);

            return $advance;
        });
    }

    /**
     * Apply a repayment against remaining balance (IQD).
     * Cash repayments credit the vault; payroll deductions do not (handled in payroll net).
     */
    public function repay(EmployeeAdvance $advance, float|int|string $amountIqd): EmployeeAdvance
    {
        if (! $advance->isOpen()) {
            throw new InvalidArgumentException('Only open advances can be repaid.');
        }

        $pay = round((float) $amountIqd, 2);
        if ($pay <= 0) {
            throw new InvalidArgumentException('Repayment amount must be greater than zero.');
        }

        $remaining = round((float) $advance->remaining_iqd, 2);
        if ($pay > $remaining) {
            throw new InvalidArgumentException('Repayment cannot exceed remaining amount.');
        }

        return DB::transaction(function () use ($advance, $pay, $remaining) {
            $advance->remaining_iqd = round($remaining - $pay, 2);
            if ((float) $advance->remaining_iqd <= 0) {
                $advance->remaining_iqd = 0;
                $advance->status = EmployeeAdvance::STATUS_REPAID;
            }
            $advance->save();

            if ($advance->repayment_method === EmployeeAdvance::REPAY_CASH) {
                $this->postAdvanceInflow(
                    $advance,
                    $pay,
                    sprintf('Advance #%d cash repayment', $advance->id),
                );
            }

            return $advance->fresh();
        });
    }

    public function cancel(EmployeeAdvance $advance): EmployeeAdvance
    {
        if ($advance->status === EmployeeAdvance::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Advance is already cancelled.');
        }

        if ($advance->status === EmployeeAdvance::STATUS_REPAID) {
            throw new InvalidArgumentException('Fully repaid advances cannot be cancelled.');
        }

        return DB::transaction(function () use ($advance) {
            $remaining = round((float) $advance->remaining_iqd, 2);

            $advance->status = EmployeeAdvance::STATUS_CANCELLED;
            $advance->remaining_iqd = 0;
            $advance->save();

            // Reverse unreturned cash back into the vault.
            if ($remaining > 0) {
                $this->postAdvanceInflow(
                    $advance,
                    $remaining,
                    sprintf('Advance #%d cancelled — remaining returned', $advance->id),
                );
            }

            return $advance->fresh();
        });
    }

    /**
     * Open payroll-deduction remaining balance for a worker (IQD).
     */
    public function openPayrollRemainingIqd(Worker $worker): float
    {
        return round((float) EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->where('repayment_method', EmployeeAdvance::REPAY_PAYROLL)
            ->sum('remaining_iqd'), 2);
    }

    protected function postAdvanceOutflow(EmployeeAdvance $advance, float $amountIqd, ?int $createdBy): void
    {
        $vault = $this->zhakoVault();
        $rate = $this->exchangeRates->getUsdToIqd();
        if ($rate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero.');
        }

        $amountUsd = round($amountIqd / $rate, 2);
        if ($amountUsd > (float) $vault->balance_usd + 0.0001) {
            throw new InvalidArgumentException('Cannot issue advance: vault cash balance insufficient.');
        }

        $vault->balance_usd = round((float) $vault->balance_usd - $amountUsd, 2);
        $vault->balance_iqd = round((float) $vault->balance_iqd - $amountIqd, 2);
        $vault->save();

        Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $advance->project_id,
            'type' => Transaction::TYPE_ADVANCE,
            'occurred_on' => $advance->advanced_on?->toDateString() ?? now()->toDateString(),
            'amount_usd' => $amountUsd,
            'amount_iqd' => $amountIqd,
            'exchange_rate' => $rate,
            'description' => sprintf(
                'Advance #%d · %s',
                $advance->id,
                $advance->reason,
            ),
            'reference_code' => 'ADV-'.$advance->id,
            'reference_type' => $advance->getMorphClass(),
            'reference_id' => $advance->id,
            'created_by' => $createdBy ?? $advance->entered_by,
        ]);
    }

    protected function postAdvanceInflow(EmployeeAdvance $advance, float $amountIqd, string $description): void
    {
        $vault = $this->zhakoVault();
        $rate = $this->exchangeRates->getUsdToIqd();
        if ($rate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than zero.');
        }

        $amountUsd = round($amountIqd / $rate, 2);

        $vault->balance_usd = round((float) $vault->balance_usd + $amountUsd, 2);
        $vault->balance_iqd = round((float) $vault->balance_iqd + $amountIqd, 2);
        $vault->save();

        Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $advance->project_id,
            'type' => Transaction::TYPE_DEPOSIT,
            'occurred_on' => now()->toDateString(),
            'amount_usd' => $amountUsd,
            'amount_iqd' => $amountIqd,
            'exchange_rate' => $rate,
            'description' => $description,
            'reference_code' => 'ADV-RPY-'.$advance->id,
            'reference_type' => $advance->getMorphClass(),
            'reference_id' => $advance->id,
            'created_by' => $advance->entered_by,
        ]);
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
