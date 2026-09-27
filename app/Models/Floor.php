<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Floor extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'tower_id',
        'name',
    ];

    public function tower(): BelongsTo
    {
        return $this->belongsTo(Tower::class);
    }
}
