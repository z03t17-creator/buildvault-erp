<?php

namespace Tests\Feature;

use App\Models\ApartmentUnit;
use App\Models\ClientAdvance;
use App\Models\Expense;
use App\Models\Penalty;
use App\Models\Staff;
use App\Models\Transaction;
use App\Services\MayorcaWorkbookImportService;
use App\Support\SimpleXlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MayorcaReviewImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ok_review_sheets_import_slice(): void
    {
        $path = storage_path('app/imports/samples/test-mayorca-review.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        app(SimpleXlsxWriter::class)->writeSheets($path, [
            [
                'name' => 'OK_expenses',
                'headers' => ['source_sheet', 'project_guess', 'date', 'description', 'quantity', 'amount', 'currency', 'extra_note', 'your_action'],
                'rows' => [
                    ['Rayan', 'Mayorca Zhako / Rayan daily', '2026-03-16', 'nanxwardn', '', '12000', 'IQD', '', 'EDIT_OK_OR_CHANGE'],
                    ['darwaza', 'Darwaza Cornish', '2026-04-01', 'site pay', '', '50$', 'USD', '', 'EDIT_OK_OR_CHANGE'],
                ],
            ],
            [
                'name' => 'OK_penalties',
                'headers' => ['date', 'person_name', 'reason', 'amount', 'currency', 'table_guess', 'your_action'],
                'rows' => [
                    ['2026-08-08', 'zagros', 'late', '50000', 'IQD', 'penalty', 'EDIT_OK_OR_CHANGE'],
                ],
            ],
            [
                'name' => 'OK_vault_qasa',
                'headers' => ['date', 'type_of_work', 'note', 'pays_in_usd', 'pays_out_usd', 'pays_in_iqd', 'pays_out_iqd', 'table_guess', 'your_action'],
                'rows' => [
                    ['2025-07-15', 'para krdna qasa', '', '5000', '0', '0', '0', 'vault_transaction', 'EDIT_OK_OR_CHANGE'],
                ],
            ],
            [
                'name' => 'OK_client_receipts',
                'headers' => ['date', 'reference', 'note', 'villa_no', 'amount', 'currency', 'status', 'table_guess', 'your_action'],
                'rows' => [
                    ['2025-01-24', 'T1', 'AL(64+65+9)', '', '19159.059', 'IQD', 'Recieved', 'client_receipt_or_advance', 'EDIT_OK_OR_CHANGE'],
                ],
            ],
            [
                'name' => 'OK_spatial_production',
                'headers' => ['trade', 'building_block', 'floor', 'apartment', 'worker_or_crew', 'qty_if_any', 'status_guess', 'raw_cell', 'table_guess', 'your_action'],
                'rows' => [
                    ['MDF', 'B1', '1', '8', '5 hunar', '5', 'assigned_or_done', '5 hunar', 'spatial_production', 'EDIT_OK_OR_CHANGE'],
                    ['MDF', 'B1', '1', '7', 'xoman', '', 'assigned_or_done', 'xoman', 'spatial_production', 'EDIT_OK_OR_CHANGE'],
                    ['MDF', 'B2', '1', '1', 'aland', '', 'assigned_or_done', 'aland', 'spatial_production', 'EDIT_OK_OR_CHANGE'],
                ],
            ],
            [
                'name' => 'OK_people_candidates',
                'headers' => ['name', 'seen_count', 'my_guess', 'YOUR_CLASSIFY staff|worker|vendor|ignore'],
                'rows' => [
                    ['hogr', '82', 'unclassified_person', ''],
                    ['xoman', '188', 'crew', 'ignore'],
                ],
            ],
            [
                'name' => 'NEED_rayan_wastakan',
                'headers' => ['date', 'name'],
                'rows' => [
                    ['2026-03-31', 'BALLEN'],
                ],
            ],
        ]);

        $result = app(MayorcaWorkbookImportService::class)->import($path, dryRun: false);

        $this->assertSame(2, $result['counts']['expenses']);
        $this->assertSame(1, $result['counts']['penalties']);
        $this->assertSame(1, $result['counts']['vault_rows']);
        $this->assertSame(1, $result['counts']['client_advances']);
        $this->assertSame(2, $result['counts']['spatial_units']); // B1 only
        $this->assertSame(2, Expense::query()->count());
        $this->assertSame(1, Penalty::query()->count());
        $this->assertNotNull(Penalty::query()->first()->staff_id);
        $this->assertSame(1, ClientAdvance::query()->count());
        $this->assertSame(19159.06, round((float) ClientAdvance::query()->first()->amount_usd, 2));
        $this->assertGreaterThanOrEqual(1, Transaction::query()->count());
        $this->assertSame(2, ApartmentUnit::query()->count());
        $this->assertNotNull(Staff::query()->where('name', 'hogr')->first());
        $this->assertNotNull(Staff::query()->where('name', 'zagros')->first());
    }
}
