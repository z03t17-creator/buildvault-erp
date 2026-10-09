<?php

namespace App\Services;

use App\Models\ClientRetentionHold;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AuditActions;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Client→company 10%/180-day retention maturity + release.
 */
class ClientRetentionHoldService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, ClientRetentionHold>
     */
    public function markDueAsMatured(?Carbon $asOf = null): Collection
    {
        $asOf ??= now()->startOfDay();

        return DB::transaction(function () use ($asOf) {
            $due = ClientRetentionHold::query()
                ->where('status', ClientRetentionHold::STATUS_HOLDING)
                ->whereDate('maturity_date', '<=', $asOf->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($due as $hold) {
                $hold->status = ClientRetentionHold::STATUS_MATURED;
                $hold->save();
            }

            return ClientRetentionHold::query()->whereIn('id', $due->pluck('id'))->get();
        });
    }

    /**
     * Release matured client retention (insurance held against company becomes available again).
     * Dual columns preserved — no FX blend.
     */
    public function release(ClientRetentionHold $hold, ?User $actor = null): ClientRetentionHold
    {
        if ($hold->status !== ClientRetentionHold::STATUS_MATURED) {
            throw new InvalidArgumentException('Only matured client retention holds can be released.');
        }

        return DB::transaction(function () use ($hold, $actor) {
            $hold = ClientRetentionHold::query()->lockForUpdate()->findOrFail($hold->id);

            if ($hold->status !== ClientRetentionHold::STATUS_MATURED) {
                throw new InvalidArgumentException('Only matured client retention holds can be released.');
            }

            $usd = round((float) $hold->amount_usd, 2);
            $iqd = round((float) $hold->amount_iqd, 2);

            if ($hold->vault_id && ($usd > 0 || $iqd > 0)) {
                Transaction::query()->create([
                    'vault_id' => $hold->vault_id,
                    'project_id' => $hold->project_id,
                    'type' => Transaction::TYPE_INSURANCE,
                    'occurred_on' => now()->toDateString(),
                    'amount_usd' => $usd,
                    'amount_iqd' => $iqd,
                    'exchange_rate' => 0,
                    'description' => sprintf(
                        'Client retention hold #%d released (advance #%d)',
                        $hold->id,
                        $hold->client_advance_id,
                    ),
                    'reference_code' => 'CLIENT-INS-'.$hold->id,
                    'reference_type' => $hold->getMorphClass(),
                    'reference_id' => $hold->id,
                    'created_by' => $actor?->id,
                ]);
            }

            $hold->status = ClientRetentionHold::STATUS_RELEASED;
            $hold->released_at = now();
            $hold->released_amount_usd = $usd;
            $hold->released_amount_iqd = $iqd;
            $hold->save();

            $this->audit->log(
                AuditActions::CLIENT_RETENTION_RELEASED,
                sprintf('Client retention hold #%d released', $hold->id),
                $hold,
                [
                    'client_retention_hold_id' => $hold->id,
                    'amount_usd' => $usd,
                    'amount_iqd' => $iqd,
                ],
                $actor,
            );

            return $hold->fresh(['project', 'clientAdvance']);
        });
    }

    /**
     * @return Collection<int, ClientRetentionHold>
     */
    public function maturedAwaitingRelease(): Collection
    {
        return ClientRetentionHold::query()
            ->where('status', ClientRetentionHold::STATUS_MATURED)
            ->with(['project:id,name', 'clientAdvance:id,client_name'])
            ->orderBy('maturity_date')
            ->get();
    }
}
