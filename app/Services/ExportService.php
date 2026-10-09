<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Payout;
use App\Models\Project;
use App\Models\RetentionHold;
use App\Models\Worker;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportService
{
    public function __construct(
        private readonly PayrollService $payroll,
    ) {}

    /**
     * Multi-sheet project workbook: payouts, holds, documents.
     */
    public function downloadProjectExcel(Project $project): BinaryFileResponse
    {
        $project->loadMissing(['workers:id,name,project_id']);

        $payouts = Payout::query()
            ->where('project_id', $project->id)
            ->with('worker:id,name')
            ->orderByDesc('id')
            ->get();

        $holds = RetentionHold::query()
            ->where('project_id', $project->id)
            ->with('worker:id,name')
            ->orderByDesc('id')
            ->get();

        $documents = Document::query()
            ->where('project_id', $project->id)
            ->with('worker:id,name')
            ->orderByDesc('id')
            ->get();

        $sheets = [
            [
                'name' => 'Payouts',
                'headers' => ['id', 'category', 'worker', 'amount_usd', 'amount_iqd', 'exchange_rate', 'retention_holdback', 'status', 'notes'],
                'rows' => $payouts->map(fn (Payout $p) => [
                    $p->id,
                    $p->category,
                    $p->worker?->name,
                    $p->amount_usd,
                    $p->amount_iqd,
                    $p->exchange_rate,
                    $p->retention_holdback,
                    $p->status,
                    $p->notes,
                ])->all(),
            ],
            [
                'name' => 'Holds',
                'headers' => ['id', 'worker', 'amount_usd', 'hold_start', 'maturity_date', 'status', 'released_at', 'payout_id'],
                'rows' => $holds->map(fn (RetentionHold $h) => [
                    $h->id,
                    $h->worker?->name,
                    $h->amount_usd,
                    optional($h->hold_start)->toDateString(),
                    optional($h->maturity_date)->toDateString(),
                    $h->status,
                    optional($h->released_at)?->toDateTimeString(),
                    $h->payout_id,
                ])->all(),
            ],
            [
                'name' => 'Documents',
                'headers' => ['id', 'type', 'title', 'worker', 'original_name', 'mime_type', 'size_bytes', 'path'],
                'rows' => $documents->map(fn (Document $d) => [
                    $d->id,
                    $d->type,
                    $d->title,
                    $d->worker?->name,
                    $d->original_name,
                    $d->mime_type,
                    $d->size_bytes,
                    $d->path,
                ])->all(),
            ],
        ];

        File::ensureDirectoryExists(storage_path('app/tmp/exports'));
        $filename = 'project_'.$project->id.'_export_'.now()->format('Ymd_His').'.xlsx';
        $path = storage_path('app/tmp/exports/'.$filename);

        (new SimpleXlsxWriter)->writeSheets($path, $sheets);

        return response()->download($path, $this->safeFilename($project->name).'_export.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Worker profile PDF with payroll summary for a period.
     */
    public function downloadWorkerPdf(
        Worker $worker,
        CarbonInterface|string $from,
        CarbonInterface|string $to,
    ): Response {
        $worker->loadMissing(['project:id,name']);
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        $documents = Document::query()
            ->where('worker_id', $worker->id)
            ->whereIn('type', [Document::TYPE_PROFILE_PHOTO, Document::TYPE_NATIONAL_ID])
            ->orderByDesc('id')
            ->get(['id', 'type', 'title', 'original_name', 'path']);

        $payroll = $this->payroll->calculate($worker, $from, $to);

        $payouts = Payout::query()
            ->where('worker_id', $worker->id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('approved_at', [$from, $to]);
            })
            ->orderByDesc('id')
            ->get();

        $pdf = Pdf::loadView('exports.worker', [
            'worker' => $worker,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'documents' => $documents,
            'payroll' => $payroll,
            'payouts' => $payouts,
            'locale' => app()->getLocale(),
        ])->setPaper('a4');

        $name = $this->safeFilename($worker->name).'_profile_'.$from->format('Ymd').'_'.$to->format('Ymd').'.pdf';

        return $pdf->download($name);
    }

    /**
     * Localized dual-currency payout voucher PDF.
     */
    public function downloadPayoutVoucher(Payout $payout): Response
    {
        $payout->loadMissing([
            'project:id,name,location',
            'worker:id,name,role',
            'vault:id,name',
            'creator:id,name',
        ]);

        $pdf = Pdf::loadView('exports.voucher', [
            'payout' => $payout,
            'locale' => app()->getLocale(),
            'generatedAt' => now(),
        ])->setPaper('a5', 'landscape');

        return $pdf->download('voucher_payout_'.$payout->id.'.pdf');
    }

    protected function safeFilename(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name) ?: 'export';

        return trim($safe, '_');
    }
}
