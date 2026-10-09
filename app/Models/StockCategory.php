<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'notes',
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
