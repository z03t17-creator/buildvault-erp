<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    public const TYPE_PROFILE_PHOTO = 'profile_photo';

    public const TYPE_NATIONAL_ID = 'national_id';

    public const TYPE_VOUCHER = 'voucher';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_CONTRACT = 'contract';

    public const TYPE_SITE_PHOTO = 'site_photo';

    public const TYPE_SAFETY_REPORT = 'safety_report';

    public const TYPE_OTHER = 'other';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_PROFILE_PHOTO,
        self::TYPE_NATIONAL_ID,
        self::TYPE_VOUCHER,
        self::TYPE_RECEIPT,
        self::TYPE_CONTRACT,
        self::TYPE_SITE_PHOTO,
        self::TYPE_SAFETY_REPORT,
        self::TYPE_OTHER,
    ];

    public const DISK = 'uploads';

    public const MAX_KILOBYTES = 10240; // ~10 MB

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'worker_id',
        'type',
        'title',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'url',
        'is_image',
        'type_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Allowed mimes keyed by document type.
     *
     * @return array<string, list<string>>
     */
    public static function mimesForType(string $type): array
    {
        return match ($type) {
            self::TYPE_PROFILE_PHOTO, self::TYPE_SITE_PHOTO => ['jpg', 'jpeg', 'png', 'webp'],
            self::TYPE_NATIONAL_ID, self::TYPE_VOUCHER, self::TYPE_RECEIPT, self::TYPE_CONTRACT, self::TYPE_SAFETY_REPORT => ['jpg', 'jpeg', 'png', 'pdf'],
            self::TYPE_OTHER => ['jpg', 'jpeg', 'png', 'pdf', 'csv', 'xlsx', 'xls'],
            default => ['jpg', 'jpeg', 'png', 'pdf'],
        };
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => route('documents.file', $this));
    }

    protected function isImage(): Attribute
    {
        return Attribute::get(function () {
            $mime = (string) $this->mime_type;

            return str_starts_with($mime, 'image/');
        });
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn () => str_replace('_', ' ', (string) $this->type));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
