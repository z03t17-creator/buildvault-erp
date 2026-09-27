<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    public const ROLE_ENGINEER = 'engineer';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_SUBCONTRACTOR = 'subcontractor';

    public const ROLE_LABORER = 'laborer';

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_ENGINEER,
        self::ROLE_SUPERVISOR,
        self::ROLE_SUBCONTRACTOR,
        self::ROLE_LABORER,
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_LABORER,
        'daily_rate_usd' => 0,
        'overtime_rate_usd' => 0,
        'spending_limit_usd' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'role',
        'daily_rate_usd',
        'overtime_rate_usd',
        'spending_limit_usd',
        'phone',
        'national_id_number',
        'avatar_path',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_rate_usd' => 'decimal:2',
            'overtime_rate_usd' => 'decimal:2',
            'spending_limit_usd' => 'decimal:2',
        ];
    }

    /**
     * Public URL via `php artisan storage:link` → `/storage/uploads/workers/...`
     * Relative so it works regardless of APP_URL host/port.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->avatar_path) {
                return null;
            }

            return '/storage/'.ltrim($this->avatar_path, '/');
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Attendance rows (table/model fleshed out in Phase 2.3).
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function retentionHolds(): HasMany
    {
        return $this->hasMany(RetentionHold::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }
}
