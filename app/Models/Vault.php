<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vault extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'balance_usd',
        'balance_iqd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_usd' => 'decimal:2',
            'balance_iqd' => 'decimal:2',
        ];
    }
}
