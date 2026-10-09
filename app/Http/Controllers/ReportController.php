<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ReportExportService;
use App\Services\ReportService;
use App\Support\ReportTypes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExportService $exporter,
    ) {}

    public function index(): InertiaResponse
    {
        $this->authorizeViewReports();

        $user = request()->user();
        $canFinancial = $user->can('manageExports', Vault::class);
        $canStock = $user->can('viewAny', StockItem::class);

        $catalog = [];
        foreach (ReportTypes::definitions() as $type => $meta) {
            $allowed = $meta['access'] === ReportTypes::ACCESS_STOCK
                ? $canStock
                : $canFinancial;
            if (! $allowed) {
                continue;
            }
            $catalog[$meta['category']][] = [
                'type' => $type,
                'category' => $meta['category'],
                'filters' => $meta['filters'],
                'exports' => $meta['exports'],
                'href' => route('reports.show', $type),
            ];
        }

        return Inertia::render('Reports/Index', [
            'catalog' => $catalog,
            'categories' => array_values(array_filter(
                ReportTypes::categories(),
                fn (string $c) => isset($catalog[$c]),
            )),
            'can_financial' => $canFinancial,
            'can_stock' => $canStock,
            'legacy' => $canFinancial ? [
                'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'status', 'location']),
                'workers' => Worker::query()
                    ->with('project:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name', 'role', 'project_id']),
                'payouts' => Payout::query()
                    ->with(['project:id,name', 'worker:id,name'])
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(['id', 'project_id', 'worker_id', 'category', 'amount_usd', 'amount_iqd', 'status', 'created_at']),
                'default_month' => now()->format('Y-m'),
            ] : null,
        ]);
    }

    public function show(Request $request, string $type): InertiaResponse
    {
        $this->authorizeReportType($type);

        $filters = $request->only(['from', 'to', 'month', 'project_id', 'worker_id']);
        $report = $this->reports->build($type, $filters);
        $meta = ReportTypes::get($type);

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'meta' => $meta,
            'filterOptions' => $this->filterOptions($meta['filters'] ?? []),
            'exportUrls' => [
                'pdf' => route('reports.export', ['type' => $type, 'format' => 'pdf']),
                'xlsx' => route('reports.export', ['type' => $type, 'format' => 'xlsx']),
                'csv' => route('reports.export', ['type' => $type, 'format' => 'csv']),
            ],
        ]);
    }

    public function export(Request $request, string $type, string $format): Response|BinaryFileResponse|StreamedResponse
    {
        $this->authorizeReportType($type);

        if (! in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
            abort(404);
        }

        $filters = $request->only(['from', 'to', 'month', 'project_id', 'worker_id']);
        $report = $this->reports->build($type, $filters);

        return match ($format) {
            'pdf' => $this->exporter->downloadPdf($report),
            'xlsx' => $this->exporter->downloadXlsx($report),
            'csv' => $this->exporter->downloadCsv($report),
        };
    }

    /**
     * @param  list<string>  $needed
     * @return array{projects?: list<array{id: int, name: string}>, workers?: list<array{id: int, name: string, project_id: ?int}>}
     */
    protected function filterOptions(array $needed): array
    {
        $out = [];
        if (in_array('project_id', $needed, true)) {
            $out['projects'] = Project::query()->orderBy('name')->get(['id', 'name']);
        }
        if (in_array('worker_id', $needed, true)) {
            $out['workers'] = Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']);
        }

        return $out;
    }

    protected function authorizeViewReports(): void
    {
        $user = request()->user();
        if (! $user) {
            abort(403);
        }
        if ($user->can('manageExports', Vault::class) || $user->can('viewAny', StockItem::class)) {
            return;
        }
        abort(403);
    }

    protected function authorizeReportType(string $type): void
    {
        if (! ReportTypes::exists($type)) {
            abort(404);
        }

        $user = request()->user();
        $meta = ReportTypes::get($type);
        if ($meta['access'] === ReportTypes::ACCESS_STOCK) {
            $this->authorize('viewAny', StockItem::class);

            return;
        }

        $this->authorize('manageExports', Vault::class);
    }
}
