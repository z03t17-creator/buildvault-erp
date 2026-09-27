<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Phase 4.5: template downloads + stub index.
 * Upload / validation UI arrives in Phase 4.6.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $imports,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Imports/Index', [
            'types' => collect($this->imports->definitions())->map(fn ($def, $type) => [
                'type' => $type,
                'label' => $def['label'],
                'headers' => $def['headers'],
                'notes' => $def['notes'],
                'csv_url' => route('imports.templates.download', ['type' => $type, 'format' => 'csv']),
                'xlsx_url' => route('imports.templates.download', ['type' => $type, 'format' => 'xlsx']),
            ])->values(),
            'recent' => Import::query()
                ->with('creator:id,name')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'type', 'mode', 'status', 'original_filename', 'total_rows', 'success_rows', 'failed_rows', 'created_by', 'created_at']),
        ]);
    }

    public function downloadTemplate(Request $request, string $type): BinaryFileResponse
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(404);
        }

        $this->imports->definition($type);

        // Ensure templates exist (idempotent).
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
