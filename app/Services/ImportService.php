<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Import;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Worker;
use App\Support\SimpleXlsxWriter;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Bulk import framework (Phase 4.5).
 * Templates + definitions land here; full row processing arrives in 4.6.
 */
class ImportService
{
    public const TEMPLATE_DIR = 'templates/imports';

    /**
     * Template definitions: headers, sample row, validation rules (documented for 4.6).
     *
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
                    'Duplicate worker+date rows will be rejected in Phase 4.6.',
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
                    'Liquidity checks run during Phase 4.6 import processing.',
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

    /**
     * Absolute directory for committed templates.
     */
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
     * Write CSV + XLSX templates and validation rules doc for all types.
     *
     * @return list<string> written paths
     */
    public function generateTemplates(): array
    {
        File::ensureDirectoryExists($this->templateDirectory());

        $written = [];
        $rulesDoc = "# Import template validation rules\n\n";
        $rulesDoc .= "Generated for Phase 4.5. Row enforcement lands in Phase 4.6.\n\n";

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

        $rulesDoc .= "## Modes (Phase 4.6)\n\n";
        $rulesDoc .= "- **partial** — import valid rows; skip/report invalid.\n";
        $rulesDoc .= "- **atomic** — all-or-nothing; rollback on any invalid row.\n";

        File::put($this->rulesMarkdownPath(), $rulesDoc);
        $written[] = $this->rulesMarkdownPath();

        return $written;
    }

    /**
     * Skeleton: create a pending import record (file processing in 4.6).
     *
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
