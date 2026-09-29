<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\DualCurrency;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmployeeAdvanceService
{
    public function __construct(
        private readonly VaultBalanceService $balances,
    ) {}

    /**
     * @param  array{
     *     worker_id: int,
     *     project_id: int,
     *     amount?: float|int|string,
     *     amount_iqd?: float|int|string,
     *     amount_usd?: float|int|string,
     *     currency?: string,
     *     advanced_on: string,
     *     reason: string,
     *     repayment_method: string,
     *     remaining_iqd?: float|int|string|null,
     *     remaining_usd?: float|int|string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function create(array $data, ?User $actor = null): EmployeeAdvance
    {
        $worker = Worker::query()->findOrFail($data['worker_id']);
        $project = Project::query()->findOrFail($data['project_id']);

        $currency = strtoupper((string) ($data['currency'] ?? DualCurrency::IQD));
        $amount = $data['amount']
            ?? ($currency === DualCurrency::USD ? ($data['amount_usd'] ?? null) : ($data['amount_iqd'] ?? null));
        $legs = DualCurrency::legs($currency, $amount);

        $remainingKey = $currency === DualCurrency::USD ? 'remaining_usd' : 'remaining_iqd';
        $remaining = array_key_exists($remainingKey, $data) && $data[$remainingKey] !== null && $data[$remainingKey] !== ''
            ? round((float) $data[$remainingKey], 2)
            : DualCurrency::primaryAmount($legs);

        if ($remaining < 0 || $remaining > DualCurrency::primaryAmount($legs)) {
            throw new InvalidArgumentException('Remaining amount must be between 0 and the advance amount.');
        }

        $method = (string) $data['repayment_method'];
        if (! in_array($method, EmployeeAdvance::REPAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Invalid repayment method.');
        }

        $status = $remaining <= 0
            ? EmployeeAdvance::STATUS_REPAID
            : EmployeeAdvance::STATUS_OPEN;

        return DB::transaction(function () use ($data, $worker, $project, $legs, $remaining, $method, $status, $actor) {
            $advance = EmployeeAdvance::query()->create([
                'worker_id' => $worker->id,
                'project_id' => $project->id,
                'amount_iqd' => $legs['amount_iqd'],
                'remaining_iqd' => $legs['currency'] === DualCurrency::IQD ? $remaining : 0,
                'amount_usd' => $legs['amount_usd'],
                'remaining_usd' => $legs['currency'] === DualCurrency::USD ? $remaining : 0,
                'currency' => $legs['currency'],
                'advanced_on' => $data['advanced_on'],
                'reason' => $data['reason'],
                'repayment_method' => $method,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'entered_by' => $actor?->id,
            ]);

            $this->postAdvanceOutflow($advance, $legs, $actor?->id);

            return $advance;
        });
    }

    public function repay(EmployeeAdvance $advance, float|int|string $amount): EmployeeAdvance
    {
        if (! $advance->isOpen()) {
            throw new InvalidArgumentException('Only open advances can be repaid.');
        }

        $currency = strtoupper((string) ($advance->currency ?: DualCurrency::IQD));
        $pay = round((float) $amount, 2);
        if ($pay <= 0) {
            throw new InvalidArgumentException('Repayment amount must be greater than zero.');
        }

        $remaining = $currency === DualCurrency::USD
            ? round((float) $advance->remaining_usd, 2)
            : round((float) $advance->remaining_iqd, 2);

        if ($pay > $remaining) {
            throw new InvalidArgumentException('Repayment cannot exceed remaining amount.');
        }

        return DB::transaction(function () use ($advance, $pay, $remaining, $currency) {
            if ($currency === DualCurrency::USD) {
                $advance->remaining_usd = round($remaining - $pay, 2);
                if ((float) $advance->remaining_usd <= 0) {
                    $advance->remaining_usd = 0;
                    $advance->status = EmployeeAdvance::STATUS_REPAID;
                }
            } else {
                $advance->remaining_iqd = round($remaining - $pay, 2);
                if ((float) $advance->remaining_iqd <= 0) {
                    $advance->remaining_iqd = 0;
                    $advance->status = EmployeeAdvance::STATUS_REPAID;
                }
            }
            $advance->save();

            if ($advance->repayment_method === EmployeeAdvance::REPAY_CASH) {
                $this->postAdvanceInflow(
                    $advance,
                    DualCurrency::legs($currency, $pay),
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
            $currency = strtoupper((string) ($advance->currency ?: DualCurrency::IQD));
            $remaining = $currency === DualCurrency::USD
                ? round((float) $advance->remaining_usd, 2)
                : round((float) $advance->remaining_iqd, 2);

            $advance->status = EmployeeAdvance::STATUS_CANCELLED;
            $advance->remaining_iqd = 0;
            $advance->remaining_usd = 0;
            $advance->save();

            if ($remaining > 0) {
                $this->postAdvanceInflow(
                    $advance,
                    DualCurrency::legs($currency, $remaining),
                    sprintf('Advance #%d cancelled — remaining returned', $advance->id),
                );
            }

            return $advance->fresh();
        });
    }

    public function openPayrollRemainingIqd(Worker $worker): float
    {
        return round((float) EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->where('repayment_method', EmployeeAdvance::REPAY_PAYROLL)
            ->sum('remaining_iqd'), 2);
    }

    public function openPayrollRemainingUsd(Worker $worker): float
    {
        return round((float) EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->where('status', EmployeeAdvance::STATUS_OPEN)
            ->where('repayment_method', EmployeeAdvance::REPAY_PAYROLL)
            ->sum('remaining_usd'), 2);
    }

    /**
     * @param  array{currency: string, amount_usd: float, amount_iqd: float}  $legs
     */
    protected function postAdvanceOutflow(EmployeeAdvance $advance, array $legs, ?int $createdBy): void
    {
        $vault = $this->zhakoVault();

        if ($legs['currency'] === DualCurrency::USD && $legs['amount_usd'] > (float) $vault->balance_usd + 0.0001) {
            throw new InvalidArgumentException('Cannot issue advance: Available Cash USD insufficient.');
        }
        if ($legs['currency'] === DualCurrency::IQD && $legs['amount_iqd'] > (float) $vault->balance_iqd + 0.0001) {
            throw new InvalidArgumentException('Cannot issue advance: Available Cash IQD insufficient.');
        }

        $txn = Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $advance->project_id,
            'type' => Transaction::TYPE_ADVANCE,
            'direction' => 'out',
            'occurred_on' => $advance->advanced_on?->toDateString() ?? now()->toDateString(),
            'amount_usd' => $legs['amount_usd'],
            'amount_iqd' => $legs['amount_iqd'],
            'exchange_rate' => 0,
            'description' => sprintf('Advance #%d · %s', $advance->id, $advance->reason),
            'reference_code' => 'ADV-'.$advance->id,
            'reference_type' => $advance->getMorphClass(),
            'reference_id' => $advance->id,
            'created_by' => $createdBy ?? $advance->entered_by,
        ]);
        $this->balances->apply($txn, $vault);
    }

    /**
     * @param  array{currency: string, amount_usd: float, amount_iqd: float}  $legs
     */
    protected function postAdvanceInflow(EmployeeAdvance $advance, array $legs, string $description): void
    {
        $vault = $this->zhakoVault();

        $txn = Transaction::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $advance->project_id,
            'type' => Transaction::TYPE_DEPOSIT,
            'direction' => 'in',
            'occurred_on' => now()->toDateString(),
            'amount_usd' => $legs['amount_usd'],
            'amount_iqd' => $legs['amount_iqd'],
            'exchange_rate' => 0,
            'description' => $description,
            'reference_code' => 'ADV-RPY-'.$advance->id,
            'reference_type' => $advance->getMorphClass(),
            'reference_id' => $advance->id,
            'created_by' => $advance->entered_by,
        ]);
        $this->balances->apply($txn, $vault);
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
