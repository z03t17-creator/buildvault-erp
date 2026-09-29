<?php

namespace App\Console\Commands;

use App\Services\BusinessDataWipeService;
use App\Services\MayorcaWorkbookImportService;
use App\Support\SimpleXlsxWriter;
use Illuminate\Console\Command;

class ImportMayorcaWorkbook extends Command
{
    protected $signature = 'mayorca:import
        {--path= : Absolute or storage-relative path to the workbook}
        {--dry-run : Preview counts without writing}
        {--wipe : Wipe business data before import (keeps roles/core users)}
        {--commit : Confirm destructive wipe + import (required with --wipe unless --dry-run)}
        {--write-sample : Write the bundled sample workbook to the default path and exit}';

    protected $description = 'Wipe business data (optional) and import the Mayorca Zhako Excel workbook';

    public function handle(
        MayorcaWorkbookImportService $importer,
        BusinessDataWipeService $wiper,
        SimpleXlsxWriter $writer,
    ): int {
        if ($this->option('write-sample')) {
            return $this->writeSample($writer);
        }

        $path = $this->resolvePath((string) ($this->option('path') ?: ''));
        // Prefer bundled resources sample when storage copy is missing.
        if (! is_file($path)) {
            $bundled = base_path('resources/imports/samples/hsabati-mayorca-zhako.xlsx');
            if (is_file($bundled)) {
                $storage = MayorcaWorkbookImportService::sampleAbsolutePath();
                if (! is_dir(dirname($storage))) {
                    mkdir(dirname($storage), 0775, true);
                }
                copy($bundled, $storage);
                $path = $storage;
            }
        }
        $dryRun = (bool) $this->option('dry-run');
        $wipe = (bool) $this->option('wipe');
        $commit = (bool) $this->option('commit');

        if ($wipe && ! $dryRun && ! $commit) {
            $this->error('Refusing wipe without --commit (or use --dry-run).');

            return self::FAILURE;
        }

        if ($wipe) {
            $this->info($dryRun ? 'Dry-run wipe preview…' : 'Wiping business data…');
            $wipeResult = $wiper->wipe(dryRun: $dryRun);
            $this->line('Kept users: '.implode(', ', $wipeResult['kept_users']));
            foreach ($wipeResult['wiped_tables'] as $table => $count) {
                if ($count > 0) {
                    $this->line(sprintf('  %s: %d', $table, $count));
                }
            }
        }

        if (! is_file($path)) {
            $this->error("Workbook not found: {$path}");
            $this->line('Generate sample with: php artisan mayorca:import --write-sample');

            return self::FAILURE;
        }

        $this->info(($dryRun ? 'Dry-run import: ' : 'Importing: ').$path);
        $result = $importer->import($path, dryRun: $dryRun);

        $this->table(
            ['Metric', 'Count'],
            collect($result['counts'])->map(fn ($v, $k) => [$k, $v])->values()->all()
        );
        $this->info('People (unclassified): '.$result['people']);
        $this->info('Sheets: '.implode(', ', $result['sheets']));
        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($dryRun) {
            $this->comment('Dry-run only — no database writes from import.');
        } else {
            $this->info('Import committed.');
        }

        return self::SUCCESS;
    }

    protected function resolvePath(string $path): string
    {
        if ($path === '') {
            return MayorcaWorkbookImportService::sampleAbsolutePath();
        }
        if (is_file($path)) {
            return $path;
        }
        $storage = storage_path('app/'.ltrim($path, '/'));
        if (is_file($storage)) {
            return $storage;
        }

        return $path;
    }

    protected function writeSample(SimpleXlsxWriter $writer): int
    {
        $path = MayorcaWorkbookImportService::sampleAbsolutePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $writer->writeSheets($path, $this->sampleSheets());

        $bundledDir = base_path('resources/imports/samples');
        if (! is_dir($bundledDir)) {
            mkdir($bundledDir, 0775, true);
        }
        copy($path, $bundledDir.'/hsabati-mayorca-zhako.xlsx');

        $this->info('Wrote sample workbook: '.$path);
        $this->line('Also copied to: '.$bundledDir.'/hsabati-mayorca-zhako.xlsx');

        return self::SUCCESS;
    }

    /**
     * Reproducible Mayorca-shaped sample matching the Phase 5 sheet map.
     *
     * @return list<array{name: string, headers: list<string>, rows: list<list<string|int|float|null>>}>
     */
    protected function sampleSheets(): array
    {
        return [
            [
                'name' => 'Rayan hisabaty rozhana',
                'headers' => ['Date', 'Description', 'Amount'],
                'rows' => [
                    ['2025-01-05', 'Fuel truck', '250000'],
                    ['2025-01-06', 'Office tea', '15$'],
                    ['2025-01-08', 'Transport', '120000'],
                ],
            ],
            [
                'name' => 'Qasa',
                'headers' => ['Date', 'Description', 'In USD', 'Out USD', 'In IQD', 'Out IQD'],
                'rows' => [
                    ['2025-01-01', 'Opening / client deposit', '5000', '', '10000000', ''],
                    ['2025-01-03', 'Materials purchase', '', '800', '', ''],
                    ['2025-01-04', 'Daily expense', '', '', '', '500000'],
                    ['2025-01-10', 'Client money in', '2000', '', '', ''],
                ],
            ],
            [
                'name' => 'HISABAT',
                'headers' => ['Date', 'Note', 'Amount'],
                'rows' => [
                    ['2024-01-01', 'Legacy abandoned row', '100$'],
                ],
            ],
            [
                'name' => 'Paray wasta',
                'headers' => ['Date', 'Note', 'Adil laminate', 'Hunar', 'Hogr'],
                'rows' => [
                    ['2025-02-01', 'Villa 12 advance', '200$', '150$', '100000'],
                    ['2025-02-15', 'Villa 18 advance', '50$', '', '200$'],
                ],
            ],
            [
                'name' => 'gharamai staf',
                'headers' => ['Name', 'Reason', 'Price', 'Date'],
                'rows' => [
                    ['Adil laminate', 'Late materials', '50000', '2025-02-20'],
                    ['Hunar', 'Damage door', '25$', '2025-02-22'],
                ],
            ],
            [
                'name' => 'MDF',
                'headers' => ['Floor', 'A1', 'A2', 'A3', 'A4'],
                'rows' => [
                    ['1', 'Adil laminate', 'xoman', 'Hunar', ''],
                    ['2', 'Hogr', 'Mzgin', 'xoman', 'Aland'],
                    ['3', 'خۆمان', 'Adil laminate', '', 'Hunar'],
                ],
            ],
            [
                'name' => 'laminate',
                'headers' => ['Floor', 'A1', 'A2', 'A3'],
                'rows' => [
                    ['1', 'Hunar', 'xoman', 'Adil laminate'],
                    ['2', 'Mzgin', 'Hogr', ''],
                ],
            ],
            [
                'name' => 'مەتخەل',
                'headers' => ['Floor', 'A1', 'A2'],
                'rows' => [
                    ['1', 'xoman', 'Aland'],
                    ['2', 'Hunar', 'xoman'],
                ],
            ],
            [
                'name' => 'rayan ,wastakan',
                'headers' => ['Date', 'Description', 'Amount'],
                'rows' => [
                    ['2025-03-01', 'Wasta tools', '80$'],
                    ['2025-03-02', 'Site lunch', '90000'],
                ],
            ],
            [
                'name' => 'miran city',
                'headers' => ['Date', 'Description', 'Amount'],
                'rows' => [
                    ['2024-11-01', 'End project materials', '300$'],
                    ['2024-11-05', 'Final labor', '1500000'],
                ],
            ],
            [
                'name' => 'darwaza cornish 507+505',
                'headers' => ['Date', 'Description', 'Amount'],
                'rows' => [
                    ['2024-10-10', 'Cornish leftover pay', '500$'],
                ],
            ],
            [
                'name' => 'zanst+sozyar',
                'headers' => ['Date', 'Description', 'Amount'],
                'rows' => [
                    ['2024-09-01', 'Zanst site expense', '120$'],
                ],
            ],
            [
                'name' => 'deryn',
                'headers' => ['Date', 'Description', 'Amount'],
                'rows' => [
                    ['2024-08-15', 'Deryn close-out', '200000'],
                ],
            ],
            [
                'name' => 'PARYA',
                'headers' => ['Item', 'Qty', 'UnitPrice$', 'Total'],
                'rows' => [
                    ['MDF sheet', '10', '25', '250'],
                    ['Laminate pack', '4', '40', '160'],
                    ['Glue', '20', '3', '60'],
                ],
            ],
            [
                'name' => 'ESTIMATION',
                'headers' => ['Villa', 'Description', 'Amount', 'Person'],
                'rows' => [
                    ['12', 'Doors package', '1200$', 'Adil laminate'],
                    ['18', 'Floor m2', '800$', 'Hunar'],
                ],
            ],
            [
                'name' => 'Summery',
                'headers' => ['Client', 'Status', 'Amount USD', 'Notes'],
                'rows' => [
                    ['Mayorca Client A', 'Received', '10000$', 'Advance 1'],
                    ['Mayorca Client B', 'Not received', '4000$', 'Pending'],
                    ['Mayorca Client C', 'Received', '2500$', 'Advance 2'],
                ],
            ],
        ];
    }
}
