<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Models\Vault;
use App\Services\ImportService;
use App\Services\MayorcaWorkbookImportService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Phase 4.5 templates + Phase 4.6 upload / validation / rollback.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $imports,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('manageImports', Vault::class);

        $bundledWorkbook = MayorcaWorkbookImportService::bundledSampleAbsolutePath();

        return Inertia::render('Imports/Index', [
            'types' => collect($this->imports->definitions())->map(fn ($def, $type) => [
                'type' => $type,
                'label' => $def['label'],
                'headers' => $def['headers'],
                'notes' => $def['notes'],
                'csv_url' => route('imports.templates.download', ['type' => $type, 'format' => 'csv']),
                'xlsx_url' => route('imports.templates.download', ['type' => $type, 'format' => 'xlsx']),
            ])->values(),
            'modes' => Import::MODES,
            'recent' => Import::query()
                ->with('creator:id,name')
                ->orderByDesc('id')
                ->limit(20)
                ->get([
                    'id', 'type', 'mode', 'status', 'original_filename',
                    'total_rows', 'success_rows', 'failed_rows',
                    'created_by', 'created_at', 'finished_at', 'notes',
                ]),
            'canImportMayorca' => $request->user()?->hasRole(Roles::SUPER_ADMIN) ?? false,
            'workbookBundled' => is_file($bundledWorkbook),
        ]);
    }

    public function store(StoreImportRequest $request): RedirectResponse
    {
        $this->authorize('manageImports', Vault::class);

        $import = $this->imports->processUpload(
            $request->file('file'),
            $request->string('type')->toString(),
            $request->string('mode')->toString(),
            $request->user()?->id,
        );

        $message = match ($import->status) {
            Import::STATUS_COMPLETED => "Import #{$import->id} finished: {$import->success_rows} imported, {$import->failed_rows} failed.",
            Import::STATUS_FAILED => "Import #{$import->id} failed: {$import->failed_rows} invalid row(s). See error report.",
            default => "Import #{$import->id} status: {$import->status}.",
        };

        return redirect()
            ->route('imports.show', $import)
            ->with('success', $message);
    }

    public function show(Import $import): Response
    {
        $this->authorize('manageImports', Vault::class);

        $import->load(['creator:id,name', 'details' => fn ($q) => $q->orderBy('row_number')]);

        return Inertia::render('Imports/Show', [
            'import' => [
                'id' => $import->id,
                'type' => $import->type,
                'mode' => $import->mode,
                'status' => $import->status,
                'original_filename' => $import->original_filename,
                'total_rows' => $import->total_rows,
                'success_rows' => $import->success_rows,
                'failed_rows' => $import->failed_rows,
                'notes' => $import->notes,
                'started_at' => $import->started_at,
                'finished_at' => $import->finished_at,
                'created_at' => $import->created_at,
                'creator' => $import->creator,
                'can_rollback' => $this->imports->canRollback($import),
                'details' => $import->details->map(fn ($d) => [
                    'id' => $d->id,
                    'row_number' => $d->row_number,
                    'status' => $d->status,
                    'payload' => $d->payload,
                    'errors' => $d->errors,
                    'record_type' => $d->record_type ? class_basename($d->record_type) : null,
                    'record_id' => $d->record_id,
                ]),
            ],
        ]);
    }

    public function rollback(Import $import): RedirectResponse
    {
        $this->authorize('manageImports', Vault::class);

        try {
            $this->imports->rollback($import);
        } catch (Throwable $e) {
            return redirect()
                ->route('imports.show', $import)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('imports.show', $import)
            ->with('success', "Import #{$import->id} rolled back where safe.");
    }

    public function downloadTemplate(Request $request, string $type): BinaryFileResponse
    {
        $this->authorize('manageImports', Vault::class);

        $format = strtolower((string) $request->query('format', 'csv'));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(404);
        }

        $this->imports->definition($type);

        if (! is_file($this->imports->csvPath($type)) || ! is_file($this->imports->xlsxPath($type))) {
            $this->imports->generateTemplates();
        }

        $path = $format === 'xlsx'
            ? $this->imports->xlsxPath($type)
            : $this->imports->csvPath($type);

        abort_unless(is_file($path), 404);

        $mime = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response()->download($path, "{$type}_import_template.{$format}", [
            'Content-Type' => $mime,
        ]);
    }
}
