<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stub for Worker::payouts() — schema lands in Phase 3.
 */
class Payout extends Model
{
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
