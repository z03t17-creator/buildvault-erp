<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Worker extends Model
{
    use SoftDeletes;

    public const LABOR_KIND_UNCLASSIFIED = 'unclassified';

    public const LABOR_KIND_STAFF = 'staff';

    public const LABOR_KIND_WORKER = 'worker';

    /** @var list<string> */
    public const LABOR_KINDS = [
        self::LABOR_KIND_UNCLASSIFIED,
        self::LABOR_KIND_STAFF,
        self::LABOR_KIND_WORKER,
    ];

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
        'labor_kind' => self::LABOR_KIND_UNCLASSIFIED,
        'daily_rate_usd' => 0,
        'overtime_rate_usd' => 0,
        'manual_ot_hours' => 0,
        'spending_limit_usd' => 0,
        'monthly_salary_usd' => 0,
        'monthly_salary_iqd' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'user_id',
        'name',
        'role',
        'labor_kind',
        'rate_unit',
        'rate_currency',
        'unit_rate',
        'monthly_salary_usd',
        'monthly_salary_iqd',
        'classified_at',
        'classified_by',
        'daily_rate_usd',
        'overtime_rate_usd',
        'manual_ot_hours',
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
            'manual_ot_hours' => 'decimal:2',
            'spending_limit_usd' => 'decimal:2',
            'unit_rate' => 'decimal:4',
            'monthly_salary_usd' => 'decimal:2',
            'monthly_salary_iqd' => 'decimal:2',
            'classified_at' => 'datetime',
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

    public function isStaff(): bool
    {
        return $this->labor_kind === self::LABOR_KIND_STAFF;
    }

    public function isEmployee(): bool
    {
        return $this->labor_kind === self::LABOR_KIND_WORKER;
    }

    public function isUnclassified(): bool
    {
        return $this->labor_kind === self::LABOR_KIND_UNCLASSIFIED
            || $this->labor_kind === null
            || $this->labor_kind === '';
    }

    /**
     * User (or Admin) sets Staff vs Worker — system never guesses.
     */
    public function classify(string $laborKind, ?int $classifiedBy = null): void
    {
        if (! in_array($laborKind, [self::LABOR_KIND_STAFF, self::LABOR_KIND_WORKER], true)) {
            throw new InvalidArgumentException('labor_kind must be staff or worker.');
        }

        $this->labor_kind = $laborKind;
        $this->classified_at = now();
        $this->classified_by = $classifiedBy;
        $this->save();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

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

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    public function staffStatements(): HasMany
    {
        return $this->hasMany(StaffStatement::class);
    }

    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function assignedApartmentUnits(): HasMany
    {
        return $this->hasMany(ApartmentUnit::class, 'assigned_worker_id');
    }
}
