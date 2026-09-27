<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stub for Worker::attendances() — schema lands in Phase 2.3.
 */
class Attendance extends Model
{
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
