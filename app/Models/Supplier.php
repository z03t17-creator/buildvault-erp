<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'notes',
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
