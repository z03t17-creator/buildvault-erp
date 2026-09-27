<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Floor;
use App\Models\Import;
use App\Models\ImportDetail;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Worker;
use App\Support\SimpleXlsxReader;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

/**
 * Bulk import: templates (4.5) + row validation / apply / rollback (4.6).
 */
class ImportService
{
    public const TEMPLATE_DIR = 'templates/imports';

    public const STORAGE_DISK = 'uploads';

    public const STORAGE_DIR = 'imports';

    public function __construct(
        private readonly PayoutService $payouts,
    ) {}

    /**
     * @return array<string, array{
     *     label: string,
     *     headers: list<string>,
     *     sample: list<string>,
     *     rules: array<string, string>,
     *     notes: list<string>,
     * }>
     */
    public function definitions(): array
    {
        return [
            Import::TYPE_WORKERS => [
                'label' => 'Workers',
                'headers' => [
                    'name',
                    'project_name',
                    'role',
                    'daily_rate_usd',
                    'overtime_rate_usd',
                    'spending_limit_usd',
                    'phone',
                    'national_id_number',
                ],
                'sample' => [
                    'Ali Hassan',
                    'Zhako Demo Tower',
                    Worker::ROLE_LABORER,
                    '50.00',
                    '75.00',
                    '0',
                    '+9647500000000',
                    'NID-001',
                ],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'project_name' => 'nullable|string|exists:projects,name',
                    'role' => 'nullable|in:'.implode(',', Worker::ROLES),
                    'daily_rate_usd' => 'nullable|numeric|min:0',
                    'overtime_rate_usd' => 'nullable|numeric|min:0',
                    'spending_limit_usd' => 'nullable|numeric|min:0',
                    'phone' => 'nullable|string|max:64',
                    'national_id_number' => 'nullable|string|max:64',
                ],
                'notes' => [
                    'project_name must match an existing project (or leave blank for unassigned).',
                    'role defaults to laborer when empty.',
                ],
            ],
            Import::TYPE_PROJECTS => [
                'label' => 'Projects',
                'headers' => [
                    'name',
                    'location',
                    'status',
                    'total_budget_usd',
                    'start_date',
                    'end_date',
                    'description',
                ],
                'sample' => [
                    'New Site A',
                    'Erbil',
                    Project::STATUS_PLANNING,
                    '250000',
                    '2026-10-01',
                    '2027-10-01',
                    'Phase 1 construction',
                ],
                'rules' => [
                    'name' => 'required|string|max:255|unique:projects,name',
                    'location' => 'nullable|string|max:255',
                    'status' => 'nullable|in:'.implode(',', Project::STATUSES),
                    'total_budget_usd' => 'nullable|numeric|min:0',
                    'start_date' => 'nullable|date',
                    'end_date' => 'nullable|date|after_or_equal:start_date',
                    'description' => 'nullable|string',
                ],
                'notes' => [
                    'status defaults to planning when empty.',
                    'Dates use YYYY-MM-DD.',
                ],
            ],
            Import::TYPE_ATTENDANCES => [
                'label' => 'Attendances',
                'headers' => [
                    'worker_name',
                    'date',
                    'check_in',
                    'check_out',
                    'status',
                    'late_minutes',
                    'overtime_hours',
                    'floor_name',
                ],
                'sample' => [
                    'Ali Hassan',
                    '2026-09-15',
                    '08:00',
                    '17:00',
                    Attendance::STATUS_PRESENT,
                    '0',
                    '0',
                    'Floor 1',
                ],
                'rules' => [
                    'worker_name' => 'required|string|exists:workers,name',
                    'date' => 'required|date',
                    'check_in' => 'nullable|date_format:H:i',
                    'check_out' => 'nullable|date_format:H:i|after:check_in',
                    'status' => 'nullable|in:'.implode(',', Attendance::STATUSES),
                    'late_minutes' => 'nullable|integer|min:0',
                    'overtime_hours' => 'nullable|numeric|min:0',
                    'floor_name' => 'nullable|string|exists:floors,name',
                ],
                'notes' => [
                    'worker_name must match an existing worker.',
                    'Duplicate worker+date rows are rejected.',
                ],
            ],
            Import::TYPE_PAYOUTS => [
                'label' => 'Payouts',
                'headers' => [
                    'project_name',
                    'category',
                    'amount_usd',
                    'worker_name',
                    'retention_holdback',
                    'notes',
                ],
                'sample' => [
                    'Zhako Demo Tower',
                    Payout::CATEGORY_PAYROLL,
                    '1000.00',
                    'Ali Hassan',
                    '',
                    'September payroll',
                ],
                'rules' => [
                    'project_name' => 'required|string|exists:projects,name',
                    'category' => 'required|in:'.implode(',', Payout::CATEGORIES),
                    'amount_usd' => 'required|numeric|gt:0',
                    'worker_name' => 'nullable|string|exists:workers,name',
                    'retention_holdback' => 'nullable|numeric|min:0',
                    'notes' => 'nullable|string|max:2000',
                ],
                'notes' => [
                    'Creates pending payouts only; approve/reconcile stays in the UI/services.',
                    'Liquidity checks run during import processing.',
                    'Empty retention_holdback uses default insurance % for payroll+worker.',
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, headers: list<string>, sample: list<string>, rules: array<string, string>, notes: list<string>}
     */
    public function definition(string $type): array
    {
        $definitions = $this->definitions();
        if (! isset($definitions[$type])) {
            throw new InvalidArgumentException("Unknown import type [{$type}].");
        }

        return $definitions[$type];
    }

    public function templateDirectory(): string
    {
        return resource_path(self::TEMPLATE_DIR);
    }

    public function csvPath(string $type): string
    {
        return $this->templateDirectory().'/'.$type.'.csv';
    }

    public function xlsxPath(string $type): string
    {
        return $this->templateDirectory().'/'.$type.'.xlsx';
    }

    public function rulesMarkdownPath(): string
    {
        return $this->templateDirectory().'/VALIDATION_RULES.md';
    }

    /**
     * @return list<string>
     */
    public function generateTemplates(): array
    {
        File::ensureDirectoryExists($this->templateDirectory());

        $written = [];
        $rulesDoc = "# Import template validation rules\n\n";
        $rulesDoc .= "Phase 4.5 templates · Phase 4.6 enforces these rules on upload.\n\n";

        foreach ($this->definitions() as $type => $def) {
            $csv = $this->csvPath($type);
            $this->writeCsv($csv, $def['headers'], [$def['sample']]);
            $written[] = $csv;

            $xlsx = $this->xlsxPath($type);
            (new SimpleXlsxWriter)->write($xlsx, $def['headers'], [$def['sample']]);
            $written[] = $xlsx;

            $rulesDoc .= '## '.$def['label']." (`{$type}`)\n\n";
            $rulesDoc .= "Headers: `".implode('`, `', $def['headers'])."`\n\n";
            $rulesDoc .= "| Column | Rule |\n|---|---|\n";
            foreach ($def['rules'] as $col => $rule) {
                $rulesDoc .= '| `'.$col.'` | `'.$rule."` |\n";
            }
            $rulesDoc .= "\n";
            foreach ($def['notes'] as $note) {
                $rulesDoc .= '- '.$note."\n";
            }
            $rulesDoc .= "\n";
        }

        $rulesDoc .= "## Modes\n\n";
        $rulesDoc .= "- **partial** — import valid rows; skip/report invalid.\n";
        $rulesDoc .= "- **atomic** — all-or-nothing; no rows imported if any row is invalid.\n";

        File::put($this->rulesMarkdownPath(), $rulesDoc);
        $written[] = $this->rulesMarkdownPath();

        return $written;
    }

    /**
     * @param  array{type: string, mode?: string, original_filename?: string|null, stored_path?: string|null, created_by?: int|null, notes?: string|null}  $data
     */
    public function createPending(array $data): Import
    {
        $type = (string) $data['type'];
        $this->definition($type);

        $mode = $data['mode'] ?? Import::MODE_PARTIAL;
        if (! in_array($mode, Import::MODES, true)) {
            throw new InvalidArgumentException('Import mode must be partial or atomic.');
        }

        return Import::query()->create([
            'type' => $type,
            'mode' => $mode,
            'status' => Import::STATUS_PENDING,
            'original_filename' => $data['original_filename'] ?? null,
            'stored_path' => $data['stored_path'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Upload, validate, and apply rows according to mode.
     */
    public function processUpload(
        UploadedFile $file,
        string $type,
        string $mode = Import::MODE_PARTIAL,
        ?int $userId = null,
    ): Import {
        $this->definition($type);
        if (! in_array($mode, Import::MODES, true)) {
            throw new InvalidArgumentException('Import mode must be partial or atomic.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (! in_array($extension, ['csv', 'xlsx', 'txt'], true)) {
            throw new InvalidArgumentException('Import file must be CSV or XLSX.');
        }

        $storedName = now()->format('YmdHis').'_'.uniqid().'.'.$extension;
        $storedPath = $file->storeAs(self::STORAGE_DIR.'/'.$type, $storedName, self::STORAGE_DISK);

        $import = $this->createPending([
            'type' => $type,
            'mode' => $mode,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'created_by' => $userId,
        ]);

        $absolute = Storage::disk(self::STORAGE_DISK)->path($storedPath);

        try {
            $matrix = $this->parseFile($absolute, $extension === 'txt' ? 'csv' : $extension);
            $assocRows = $this->mapRows($type, $matrix);
            $this->runImport($import, $assocRows);
        } catch (Throwable $e) {
            $import->update([
                'status' => Import::STATUS_FAILED,
                'notes' => 'Parse error: '.$e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $import->fresh(['details', 'creator']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function runImport(Import $import, array $rows): Import
    {
        $import->update([
            'status' => Import::STATUS_PROCESSING,
            'started_at' => now(),
            'total_rows' => count($rows),
            'success_rows' => 0,
            'failed_rows' => 0,
        ]);

        $import->details()->delete();

        $validated = [];
        $batchKeys = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header is row 1
            $result = $this->validateRow($import->type, $row, $batchKeys);

            ImportDetail::query()->create([
                'import_id' => $import->id,
                'row_number' => $rowNumber,
                'status' => $result['ok'] ? ImportDetail::STATUS_VALID : ImportDetail::STATUS_INVALID,
                'payload' => $result['payload'],
                'errors' => $result['errors'],
            ]);

            $validated[] = [
                'row_number' => $rowNumber,
                'ok' => $result['ok'],
                'payload' => $result['payload'],
                'errors' => $result['errors'],
            ];
        }

        $invalidCount = collect($validated)->where('ok', false)->count();

        if ($import->mode === Import::MODE_ATOMIC && $invalidCount > 0) {
            $import->details()
                ->where('status', ImportDetail::STATUS_VALID)
                ->update(['status' => ImportDetail::STATUS_SKIPPED]);

            $import->update([
                'status' => Import::STATUS_FAILED,
                'success_rows' => 0,
                'failed_rows' => $invalidCount,
                'finished_at' => now(),
                'notes' => 'Atomic mode: no rows imported because '.$invalidCount.' row(s) failed validation.',
            ]);

            return $import->fresh(['details']);
        }

        $success = 0;
        $failed = 0;

        $apply = function () use ($import, $validated, &$success, &$failed) {
            foreach ($validated as $item) {
                $detail = $import->details()->where('row_number', $item['row_number'])->first();
                if (! $detail) {
                    continue;
                }

                if (! $item['ok']) {
                    $failed++;
                    continue;
                }

                try {
                    $record = $this->applyRow($import->type, $item['payload'], $import->created_by);
                    $detail->update([
                        'status' => ImportDetail::STATUS_IMPORTED,
                        'record_type' => $record::class,
                        'record_id' => $record->getKey(),
                        'errors' => null,
                    ]);
                    $success++;
                } catch (Throwable $e) {
                    $detail->update([
                        'status' => ImportDetail::STATUS_INVALID,
                        'errors' => ['_apply' => [$e->getMessage()]],
                    ]);
                    $failed++;

                    if ($import->mode === Import::MODE_ATOMIC) {
                        throw $e;
                    }
                }
            }
        };

        try {
            if ($import->mode === Import::MODE_ATOMIC) {
                DB::transaction(function () use ($apply) {
                    $apply();
                });
            } else {
                $apply();
            }

            $import->update([
                'status' => $failed > 0 && $success === 0
                    ? Import::STATUS_FAILED
                    : Import::STATUS_COMPLETED,
                'success_rows' => $success,
                'failed_rows' => $failed,
                'finished_at' => now(),
                'notes' => null,
            ]);
        } catch (Throwable $e) {
            // Atomic apply failure: transaction rolled back; mark details skipped.
            $import->details()
                ->whereIn('status', [ImportDetail::STATUS_VALID, ImportDetail::STATUS_IMPORTED])
                ->update([
                    'status' => ImportDetail::STATUS_SKIPPED,
                    'record_type' => null,
                    'record_id' => null,
                ]);

            $import->update([
                'status' => Import::STATUS_FAILED,
                'success_rows' => 0,
                'failed_rows' => $import->details()->where('status', ImportDetail::STATUS_INVALID)->count(),
                'finished_at' => now(),
                'notes' => 'Atomic apply aborted: '.$e->getMessage(),
            ]);
        }

        return $import->fresh(['details']);
    }

    /**
     * Roll back imported records when still safe (pending payouts, unused workers, etc.).
     */
    public function rollback(Import $import): Import
    {
        if ($import->status === Import::STATUS_ROLLED_BACK) {
            throw new InvalidArgumentException('Import is already rolled back.');
        }

        if (! in_array($import->status, [Import::STATUS_COMPLETED, Import::STATUS_FAILED], true)) {
            throw new InvalidArgumentException('Only completed or failed imports can be rolled back.');
        }

        $imported = $import->details()
            ->where('status', ImportDetail::STATUS_IMPORTED)
            ->whereNotNull('record_id')
            ->get();

        if ($imported->isEmpty()) {
            throw new InvalidArgumentException('No imported rows to roll back.');
        }

        $unsafe = [];

        DB::transaction(function () use ($imported, &$unsafe) {
            foreach ($imported as $detail) {
                $record = $detail->record;
                if (! $record) {
                    $detail->update(['status' => ImportDetail::STATUS_ROLLED_BACK]);
                    continue;
                }

                if (! $this->canSafelyDelete($record)) {
                    $unsafe[] = 'Row '.$detail->row_number.': record is linked or no longer pending.';
                    continue;
                }

                $record->delete();
                $detail->update([
                    'status' => ImportDetail::STATUS_ROLLED_BACK,
                    'record_type' => null,
                    'record_id' => null,
                ]);
            }
        });

        $stillImported = $import->details()->where('status', ImportDetail::STATUS_IMPORTED)->count();

        $import->update([
            'status' => $stillImported === 0 ? Import::STATUS_ROLLED_BACK : Import::STATUS_COMPLETED,
            'success_rows' => $stillImported,
            'notes' => $unsafe
                ? 'Partial rollback. Skipped: '.implode(' ', $unsafe)
                : 'Rolled back all imported records.',
            'finished_at' => now(),
        ]);

        return $import->fresh(['details']);
    }

    public function canRollback(Import $import): bool
    {
        return $import->details()
            ->where('status', ImportDetail::STATUS_IMPORTED)
            ->whereNotNull('record_id')
            ->exists();
    }

    /**
     * @return list<list<string>>
     */
    public function parseFile(string $path, string $extension): array
    {
        return match ($extension) {
            'csv' => $this->parseCsv($path),
            'xlsx' => (new SimpleXlsxReader)->read($path),
            default => throw new InvalidArgumentException("Unsupported extension [{$extension}]."),
        };
    }

    /**
     * @param  list<list<string>>  $matrix
     * @return list<array<string, string|null>>
     */
    public function mapRows(string $type, array $matrix): array
    {
        if ($matrix === []) {
            return [];
        }

        $expected = $this->definition($type)['headers'];
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $matrix[0]);

        $missing = array_diff($expected, $header);
        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Missing required columns: '.implode(', ', $missing)
            );
        }

        $index = [];
        foreach ($header as $i => $name) {
            $index[$name] = $i;
        }

        $rows = [];
        for ($r = 1, $n = count($matrix); $r < $n; $r++) {
            $line = $matrix[$r];
            if ($this->rowIsEmpty($line)) {
                continue;
            }

            $assoc = [];
            foreach ($expected as $col) {
                $raw = $line[$index[$col]] ?? '';
                $value = is_string($raw) ? trim($raw) : $raw;
                $assoc[$col] = $value === '' ? null : (string) $value;
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, true>  $batchKeys
     * @return array{ok: bool, payload: array<string, mixed>, errors: array<string, list<string>>|null}
     */
    public function validateRow(string $type, array $row, array &$batchKeys = []): array
    {
        $rules = $this->definition($type)['rules'];
        $validator = Validator::make($row, $rules);

        $errors = $validator->fails() ? $validator->errors()->toArray() : [];

        // Batch-level uniqueness
        if ($type === Import::TYPE_PROJECTS && ! empty($row['name'])) {
            $key = 'project:'.mb_strtolower($row['name']);
            if (isset($batchKeys[$key])) {
                $errors['name'][] = 'Duplicate project name in this file.';
            } else {
                $batchKeys[$key] = true;
            }
        }

        if ($type === Import::TYPE_ATTENDANCES && ! empty($row['worker_name']) && ! empty($row['date'])) {
            $worker = Worker::query()->where('name', $row['worker_name'])->first();
            if ($worker) {
                $dateKey = 'att:'.$worker->id.':'.$row['date'];
                if (isset($batchKeys[$dateKey])) {
                    $errors['date'][] = 'Duplicate worker+date in this file.';
                } else {
                    $batchKeys[$dateKey] = true;
                }

                if (Attendance::query()->where('worker_id', $worker->id)->whereDate('date', $row['date'])->exists()) {
                    $errors['date'][] = 'Attendance already exists for this worker and date.';
                }
            }
        }

        $payload = $row;

        return [
            'ok' => $errors === [],
            'payload' => $payload,
            'errors' => $errors === [] ? null : $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyRow(string $type, array $payload, ?int $userId = null): Project|Worker|Attendance|Payout
    {
        return match ($type) {
            Import::TYPE_PROJECTS => Project::query()->create([
                'name' => $payload['name'],
                'location' => $payload['location'] ?? null,
                'status' => $payload['status'] ?: Project::STATUS_PLANNING,
                'total_budget_usd' => $payload['total_budget_usd'] ?? 0,
                'start_date' => $payload['start_date'] ?? null,
                'end_date' => $payload['end_date'] ?? null,
                'description' => $payload['description'] ?? null,
            ]),
            Import::TYPE_WORKERS => Worker::query()->create([
                'name' => $payload['name'],
                'project_id' => $payload['project_name']
                    ? Project::query()->where('name', $payload['project_name'])->value('id')
                    : null,
                'role' => $payload['role'] ?: Worker::ROLE_LABORER,
                'daily_rate_usd' => $payload['daily_rate_usd'] ?? 0,
                'overtime_rate_usd' => $payload['overtime_rate_usd'] ?? 0,
                'spending_limit_usd' => $payload['spending_limit_usd'] ?? 0,
                'phone' => $payload['phone'] ?? null,
                'national_id_number' => $payload['national_id_number'] ?? null,
            ]),
            Import::TYPE_ATTENDANCES => Attendance::query()->create([
                'worker_id' => Worker::query()->where('name', $payload['worker_name'])->value('id'),
                'floor_id' => ! empty($payload['floor_name'])
                    ? Floor::query()->where('name', $payload['floor_name'])->value('id')
                    : null,
                'date' => $payload['date'],
                'check_in' => $payload['check_in'] ?? null,
                'check_out' => $payload['check_out'] ?? null,
                'status' => $payload['status'] ?: Attendance::STATUS_PRESENT,
                'late_minutes' => $payload['late_minutes'] ?? 0,
                'overtime_hours' => $payload['overtime_hours'] ?? 0,
            ]),
            Import::TYPE_PAYOUTS => $this->payouts->create([
                'project_id' => Project::query()->where('name', $payload['project_name'])->value('id'),
                'category' => $payload['category'],
                'amount_usd' => $payload['amount_usd'],
                'worker_id' => ! empty($payload['worker_name'])
                    ? Worker::query()->where('name', $payload['worker_name'])->value('id')
                    : null,
                'retention_holdback' => array_key_exists('retention_holdback', $payload) && $payload['retention_holdback'] !== null && $payload['retention_holdback'] !== ''
                    ? $payload['retention_holdback']
                    : null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
            ]),
            default => throw new InvalidArgumentException("Unknown import type [{$type}]."),
        };
    }

    protected function canSafelyDelete(Project|Worker|Attendance|Payout $record): bool
    {
        if ($record instanceof Payout) {
            return $record->status === Payout::STATUS_PENDING;
        }

        if ($record instanceof Attendance) {
            return true;
        }

        if ($record instanceof Worker) {
            return ! $record->attendances()->exists()
                && ! $record->payouts()->exists();
        }

        if ($record instanceof Project) {
            return ! $record->workers()->exists()
                && ! $record->towers()->exists()
                && ! $record->payouts()->exists();
        }

        return false;
    }

    /**
     * @return list<list<string>>
     */
    protected function parseCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new InvalidArgumentException("Unable to read CSV at {$path}");
        }

        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            $rows[] = array_map(fn ($v) => is_string($v) ? $v : (string) $v, $data);
        }
        fclose($fh);

        return $rows;
    }

    /**
     * @param  list<string|null>  $line
     */
    protected function rowIsEmpty(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    protected function writeCsv(string $path, array $headers, array $rows): void
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new \RuntimeException("Unable to write CSV at {$path}");
        }

        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
    }
}
