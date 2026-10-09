<?php

namespace App\Services;

use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 15 — PDF / Excel / CSV exporters for ReportService payloads.
 */
class ReportExportService
{
    /**
     * @param  array{
     *     type: string,
     *     title_key: string,
     *     columns: list<array{key: string, label_key: string, align?: string, money?: bool}>,
     *     rows: list<array<string, mixed>>,
     *     summary: list<array{label_key: string, value: float|int|string, money?: bool}>,
     *     filters: array<string, mixed>
     * }  $report
     */
    public function downloadPdf(array $report): Response
    {
        $title = __($report['title_key']);
        $pdf = Pdf::loadView('exports.report', [
            'report' => $report,
            'title' => $title,
            'locale' => app()->getLocale(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($report['type'], 'pdf'));
    }

    /**
     * @param  array{
     *     type: string,
     *     title_key: string,
     *     columns: list<array{key: string, label_key: string, align?: string, money?: bool}>,
     *     rows: list<array<string, mixed>>,
     *     summary: list<array{label_key: string, value: float|int|string, money?: bool}>
     * }  $report
     */
    public function downloadXlsx(array $report): BinaryFileResponse
    {
        $headers = array_map(fn ($c) => __($c['label_key']), $report['columns']);
        $keys = array_map(fn ($c) => $c['key'], $report['columns']);
        $rows = [];
        foreach ($report['rows'] as $row) {
            $rows[] = array_map(fn ($key) => $row[$key] ?? '', $keys);
        }

        if ($report['summary'] !== []) {
            $rows[] = array_fill(0, count($headers), '');
            foreach ($report['summary'] as $line) {
                $summaryRow = array_fill(0, count($headers), '');
                $summaryRow[0] = __($line['label_key']);
                $summaryRow[count($headers) - 1] = $line['value'];
                $rows[] = $summaryRow;
            }
        }

        File::ensureDirectoryExists(storage_path('app/tmp/exports'));
        $path = storage_path('app/tmp/exports/'.$this->filename($report['type'], 'xlsx'));
        (new SimpleXlsxWriter)->write($path, $headers, $rows, 'Report');

        return response()->download($path, $this->filename($report['type'], 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  array{
     *     type: string,
     *     title_key: string,
     *     columns: list<array{key: string, label_key: string, align?: string, money?: bool}>,
     *     rows: list<array<string, mixed>>,
     *     summary: list<array{label_key: string, value: float|int|string, money?: bool}>
     * }  $report
     */
    public function downloadCsv(array $report): StreamedResponse
    {
        $filename = $this->filename($report['type'], 'csv');
        $headers = array_map(fn ($c) => __($c['label_key']), $report['columns']);
        $keys = array_map(fn ($c) => $c['key'], $report['columns']);

        return response()->streamDownload(function () use ($headers, $keys, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', $keys));
            }
            if ($report['summary'] !== []) {
                fputcsv($out, []);
                foreach ($report['summary'] as $line) {
                    fputcsv($out, [__($line['label_key']), $line['value']]);
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function filename(string $type, string $ext): string
    {
        return 'report_'.$type.'_'.now()->format('Ymd_His').'.'.$ext;
    }
}
