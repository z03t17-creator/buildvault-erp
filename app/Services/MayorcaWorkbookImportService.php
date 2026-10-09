<?php

namespace App\Services;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\ClientAdvance;
use App\Models\ClientRetentionHold;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use App\Support\SimpleXlsxReader;
use Carbon\Carbon;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 5 — import Mayorca / Zhako workbook sheets into dual-currency ERP.
 *
 * Every imported person name becomes labor_kind=unclassified (never guessed).
 * xoman / خۆمان → company crew on spatial grids.
 */
class MayorcaWorkbookImportService
{
    public const DEFAULT_SAMPLE_PATH = 'imports/samples/hsabati-mayorca-zhako.xlsx';

    /** @var array<string, Worker> */
    protected array $peopleByKey = [];

    /** @var array<string, Project> */
    protected array $projectsByKey = [];

    /** @var array<string, int> */
    protected array $counts = [];

    /** @var list<string> */
    protected array $warnings = [];

    public function __construct(
        private readonly SimpleXlsxReader $xlsx,
        private readonly VaultBalanceService $balances,
        private readonly RetentionMathService $retention,
        private readonly AuditLogger $audit,
    ) {}

    public static function sampleAbsolutePath(): string
    {
        return storage_path('app/'.self::DEFAULT_SAMPLE_PATH);
    }

    public static function bundledSampleAbsolutePath(): string
    {
        return base_path('resources/imports/samples/hsabati-mayorca-zhako.xlsx');
    }

    /**
     * Always prefer the repo-bundled workbook over a stale storage copy
     * (Render ephemeral FS can keep an older write-sample xlsx across boots).
     */
    public static function ensureBundledSampleSynced(): string
    {
        $storage = self::sampleAbsolutePath();
        $bundled = self::bundledSampleAbsolutePath();

        if (! is_file($bundled)) {
            return $storage;
        }

        $dir = dirname($storage);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $needsCopy = ! is_file($storage)
            || filesize($storage) !== filesize($bundled)
            || md5_file($storage) !== md5_file($bundled);

        if ($needsCopy) {
            copy($bundled, $storage);
        }

        return $storage;
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   sheets: list<string>,
     *   counts: array<string, int>,
     *   warnings: list<string>,
     *   people: int
     * }
     */
    public function import(string $path, bool $dryRun = true, ?User $actor = null): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Workbook not found: {$path}");
        }

        $sheets = $this->xlsx->readAll($path);
        $this->peopleByKey = [];
        $this->projectsByKey = [];
        $this->counts = [
            'vault_rows' => 0,
            'expenses' => 0,
            'penalties' => 0,
            'advances' => 0,
            'spatial_units' => 0,
            'client_advances' => 0,
            'projects' => 0,
            'people' => 0,
            'legacy_hisabat_skipped_rows' => 0,
            'materials_expenses' => 0,
            'estimations' => 0,
        ];
        $this->warnings = [];

        $runner = function () use ($sheets, $dryRun, $actor, $path) {
            // Preload existing people (post-wipe usually empty).
            foreach (Worker::query()->get() as $worker) {
                $this->peopleByKey[$this->nameKey($worker->name)] = $worker;
            }

            foreach ($sheets as $sheet) {
                $kind = $this->classifySheet($sheet['name']);
                match ($kind) {
                    'qasa' => $this->importQasa($sheet['rows'], $dryRun),
                    'rayan' => $this->importExpenseSheet($sheet['rows'], $dryRun, null, $sheet['name']),
                    'project_expense' => $this->importExpenseSheet(
                        $sheet['rows'],
                        $dryRun,
                        $this->projectNameForSheet($sheet['name']),
                        $sheet['name']
                    ),
                    'paray_wasta' => $this->importParayWasta($sheet['rows'], $dryRun),
                    'gharamai' => $this->importPenalties($sheet['rows'], $dryRun),
                    'spatial' => $this->importSpatial($sheet['rows'], $dryRun, $sheet['name']),
                    'parya' => $this->importParya($sheet['rows'], $dryRun),
                    'estimation' => $this->importEstimation($sheet['rows'], $dryRun),
                    'summary' => $this->importSummary($sheet['rows'], $dryRun),
                    'hisabat' => $this->importHisabatLegacy($sheet['rows'], $dryRun),
                    default => $this->warnings[] = "Unrecognized sheet skipped: {$sheet['name']}",
                };
            }

            if (! $dryRun) {
                $vault = $this->zhakoVault();
                $this->balances->rebuildVault($vault);

                $this->audit->log(
                    AuditActions::VAULT_MONEY_IN,
                    'Mayorca workbook imported',
                    null,
                    [
                        'path' => basename($path),
                        'counts' => $this->counts,
                        'warnings' => $this->warnings,
                        'actor_id' => $actor?->id,
                    ],
                );
            }

            return [
                'dry_run' => $dryRun,
                'sheets' => array_map(fn ($s) => $s['name'], $sheets),
                'counts' => $this->counts,
                'warnings' => $this->warnings,
                'people' => count($this->peopleByKey),
            ];
        };

        return $dryRun ? $runner() : DB::transaction($runner);
    }

    protected function classifySheet(string $name): string
    {
        $n = $this->norm($name);

        if ($n === 'qasa' || str_contains($n, 'qasa')) {
            return 'qasa';
        }
        if (str_contains($n, 'hisabat') && ! str_contains($n, 'rayan')) {
            return 'hisabat';
        }
        if (str_contains($n, 'gharamai') || str_contains($n, 'gharama')) {
            return 'gharamai';
        }
        if (str_contains($n, 'paray') && str_contains($n, 'wasta')) {
            return 'paray_wasta';
        }
        if ($n === 'parya' || str_starts_with($n, 'parya')) {
            return 'parya';
        }
        if (str_contains($n, 'estimation') || str_contains($n, 'estimat')) {
            return 'estimation';
        }
        if (str_contains($n, 'summery') || str_contains($n, 'summary')) {
            return 'summary';
        }
        if (
            str_contains($n, 'mdf')
            || str_contains($n, 'laminate')
            || str_contains($n, 'metxal')
            || str_contains($n, 'matxal')
            || str_contains($name, 'مەتخەل')
            || str_contains($name, 'مهتخل')
        ) {
            return 'spatial';
        }
        if (str_contains($n, 'rayan')) {
            return 'rayan';
        }
        if (
            str_contains($n, 'miran')
            || str_contains($n, 'darwaza')
            || str_contains($n, 'zanst')
            || str_contains($n, 'sozyar')
            || str_contains($n, 'deryn')
        ) {
            return 'project_expense';
        }

        return 'unknown';
    }

    protected function projectNameForSheet(string $sheetName): string
    {
        $n = $this->norm($sheetName);
        if (str_contains($n, 'miran')) {
            return 'Mirani City';
        }
        if (str_contains($n, 'darwaza')) {
            return 'Darwaza Cornish';
        }
        if (str_contains($n, 'zanst') || str_contains($n, 'sozyar')) {
            return 'Zanst + Sozyar';
        }
        if (str_contains($n, 'deryn')) {
            return 'Deryn';
        }

        return trim($sheetName) !== '' ? trim($sheetName) : 'Imported Site';
    }

    /** @param list<list<string>> $rows */
    protected function importQasa(array $rows, bool $dryRun): void
    {
        if ($rows === []) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'date' => ['date', 'rooj', 'roj', 'kata'],
            'description' => ['description', 'desc', 'note', 'notes', 'detail', 'bayani'],
            'in_usd' => ['in usd', 'money in usd', 'usd in', 'in$', 'in $'],
            'out_usd' => ['out usd', 'money out usd', 'usd out', 'out$', 'out $'],
            'in_iqd' => ['in iqd', 'money in iqd', 'iqd in', 'dinar in'],
            'out_iqd' => ['out iqd', 'money out iqd', 'iqd out', 'dinar out'],
        ]);

        $vault = $dryRun ? null : $this->zhakoVault();

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $date = $this->parseDate($this->cell($row, $map['date'] ?? null)) ?? now()->toDateString();
            $desc = $this->cell($row, $map['description'] ?? null) ?: 'Qasa entry';

            $legs = [
                ['direction' => 'in', 'currency' => 'USD', 'amount' => $this->parseNumber($this->cell($row, $map['in_usd'] ?? null))],
                ['direction' => 'out', 'currency' => 'USD', 'amount' => $this->parseNumber($this->cell($row, $map['out_usd'] ?? null))],
                ['direction' => 'in', 'currency' => 'IQD', 'amount' => $this->parseNumber($this->cell($row, $map['in_iqd'] ?? null))],
                ['direction' => 'out', 'currency' => 'IQD', 'amount' => $this->parseNumber($this->cell($row, $map['out_iqd'] ?? null))],
            ];

            foreach ($legs as $leg) {
                if ($leg['amount'] <= 0) {
                    continue;
                }
                $this->counts['vault_rows']++;
                if ($dryRun) {
                    continue;
                }
                $dual = DualCurrency::legs($leg['currency'], $leg['amount']);
                $txn = Transaction::query()->create([
                    'vault_id' => $vault->id,
                    'type' => $leg['direction'] === 'in' ? Transaction::TYPE_DEPOSIT : Transaction::TYPE_WITHDRAWAL,
                    'direction' => $leg['direction'],
                    'occurred_on' => $date,
                    'amount_usd' => $dual['amount_usd'],
                    'amount_iqd' => $dual['amount_iqd'],
                    'exchange_rate' => 0,
                    'description' => $desc,
                    'reference_code' => 'import:qasa',
                ]);
                // Defer balance apply — rebuildVault at end.
                unset($txn);
            }
        }
    }

    /** @param list<list<string>> $rows */
    protected function importExpenseSheet(array $rows, bool $dryRun, ?string $projectName, string $sheetName): void
    {
        if ($rows === []) {
            return;
        }

        $project = $dryRun
            ? null
            : $this->ensureProject($projectName ?? 'Mayorca Zhako', $projectName ? Project::STATUS_COMPLETED : Project::STATUS_ACTIVE);

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'date' => ['date', 'roj', 'rooj'],
            'description' => ['description', 'desc', 'note', 'notes', 'detail', 'bayani', 'item'],
            'amount' => ['amount', 'price', 'money', 'nrx', 'nrkh', 'total'],
        ]);

        // If headers look wrong, try positional: date | desc | amount
        if (($map['amount'] ?? null) === null && count($header) >= 3) {
            $map = ['date' => 0, 'description' => 1, 'amount' => 2];
        }

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $rawAmount = $this->cell($row, $map['amount'] ?? null);
            [$amount, $currency] = $this->parseMoneyToken($rawAmount);
            if ($amount <= 0) {
                continue;
            }
            $desc = $this->cell($row, $map['description'] ?? null) ?: $sheetName;
            $date = $this->parseDate($this->cell($row, $map['date'] ?? null)) ?? now()->toDateString();

            $this->counts['expenses']++;
            if ($dryRun) {
                continue;
            }

            $dual = DualCurrency::legs($currency, $amount);
            Expense::query()->create([
                'project_id' => $project->id,
                'vault_id' => $this->zhakoVault()->id,
                'category' => Expense::CATEGORY_OTHER,
                'amount_iqd' => $dual['amount_iqd'],
                'amount_usd' => $dual['amount_usd'],
                'exchange_rate' => 0,
                'currency' => $dual['currency'],
                'expense_date' => $date,
                'description' => $desc,
                'approval_status' => Expense::STATUS_PENDING,
                'payment_method' => Expense::PAYMENT_CASH,
            ]);
        }
    }

    /** @param list<list<string>> $rows */
    protected function importParayWasta(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);

        $header = $rows[0];
        $personCols = [];
        foreach ($header as $i => $label) {
            $label = trim((string) $label);
            if ($label === '' || $this->isMetaHeader($label)) {
                continue;
            }
            if ($this->isXoman($label)) {
                continue;
            }
            $personCols[$i] = $label;
            $this->ensurePerson($label, $dryRun);
        }

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $date = $this->parseDate($this->cell($row, 0)) ?? now()->toDateString();
            $note = $this->cell($row, 1);

            foreach ($personCols as $col => $name) {
                [$amount, $currency] = $this->parseMoneyToken($this->cell($row, $col));
                if ($amount <= 0) {
                    continue;
                }
                $this->counts['advances']++;
                if ($dryRun) {
                    continue;
                }
                $person = $this->ensurePerson($name, false);
                $dual = DualCurrency::legs($currency, $amount);
                EmployeeAdvance::query()->create([
                    'worker_id' => $person->id,
                    'project_id' => $project->id,
                    'amount_iqd' => $dual['amount_iqd'],
                    'remaining_iqd' => $dual['amount_iqd'],
                    'amount_usd' => $dual['amount_usd'],
                    'remaining_usd' => $dual['amount_usd'],
                    'currency' => $dual['currency'],
                    'advanced_on' => $date,
                    'reason' => $note !== '' ? $note : 'Paray wasta import',
                    'status' => EmployeeAdvance::STATUS_OPEN,
                    'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
                ]);
            }
        }
    }

    /** @param list<list<string>> $rows */
    protected function importPenalties(array $rows, bool $dryRun): void
    {
        if ($rows === []) {
            return;
        }

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'name' => ['name', 'worker', 'person', 'staff', 'naw'],
            'reason' => ['reason', 'note', 'notes', 'detail', 'bayani'],
            'price' => ['price', 'amount', 'money', 'nrx', 'fine'],
            'date' => ['date', 'roj', 'rooj'],
        ]);

        if (($map['name'] ?? null) === null) {
            $map = ['name' => 0, 'reason' => 1, 'price' => 2, 'date' => 3];
        }

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $name = $this->cell($row, $map['name'] ?? null);
            if ($name === '' || $this->isXoman($name)) {
                continue;
            }
            [$amount, $currency] = $this->parseMoneyToken($this->cell($row, $map['price'] ?? null));
            if ($amount <= 0) {
                continue;
            }
            $this->ensurePerson($name, $dryRun);
            $this->counts['penalties']++;
            if ($dryRun) {
                continue;
            }
            $person = $this->ensurePerson($name, false);
            $dual = DualCurrency::legs($currency, $amount);
            Penalty::query()->create([
                'worker_id' => $person->id,
                'project_id' => $project->id,
                'type' => Penalty::TYPE_OTHER,
                'reason' => $this->cell($row, $map['reason'] ?? null) ?: 'gharamai staf',
                'amount_usd' => $dual['amount_usd'],
                'amount_iqd' => $dual['amount_iqd'],
                'currency' => $dual['currency'],
                'occurred_on' => $this->parseDate($this->cell($row, $map['date'] ?? null)) ?? now()->toDateString(),
                'status' => Penalty::STATUS_APPLIED,
            ]);
        }
    }

    /** @param list<list<string>> $rows */
    protected function importSpatial(array $rows, bool $dryRun, string $sheetName): void
    {
        if (count($rows) < 2) {
            return;
        }

        $category = $this->spatialCategory($sheetName);
        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);
        $block = null;
        if (! $dryRun) {
            $block = BuildingBlock::query()->firstOrCreate(
                ['project_id' => $project->id, 'code' => 'B1'],
                ['name' => 'Block B1'],
            );
        }

        $header = $rows[0];
        $unitLabels = [];
        foreach ($header as $i => $label) {
            if ($i === 0) {
                continue;
            }
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $unitLabels[$i] = $label;
        }

        foreach (array_slice($rows, 1) as $row) {
            $floorRaw = $this->cell($row, 0);
            if ($floorRaw === '' || ! is_numeric(preg_replace('/[^0-9\-]/', '', $floorRaw))) {
                continue;
            }
            $floor = (int) preg_replace('/[^0-9\-]/', '', $floorRaw);

            foreach ($unitLabels as $col => $unitLabel) {
                $cell = trim($this->cell($row, $col));
                if ($cell === '') {
                    continue;
                }
                $this->counts['spatial_units']++;
                if ($dryRun) {
                    if (! $this->isXoman($cell)) {
                        $this->ensurePerson($cell, true);
                    }
                    continue;
                }

                $isCrew = $this->isXoman($cell);
                $workerId = null;
                if (! $isCrew) {
                    $workerId = $this->ensurePerson($cell, false)->id;
                }

                ApartmentUnit::query()->updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'category' => $category,
                        'floor_number' => $floor,
                        'unit_label' => $unitLabel,
                    ],
                    [
                        'building_block_id' => $block->id,
                        'status' => ApartmentUnit::STATUS_IN_PROGRESS,
                        'assigned_worker_id' => $workerId,
                        'is_company_crew' => $isCrew,
                    ],
                );
            }
        }
    }

    /** @param list<list<string>> $rows */
    protected function importParya(array $rows, bool $dryRun): void
    {
        if ($rows === []) {
            return;
        }

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);
        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'item' => ['item', 'material', 'name', 'description'],
            'qty' => ['qty', 'quantity', 'q'],
            'price' => ['price', 'unit', 'usd', 'unit price', 'unitprice'],
            'total' => ['total', 'amount'],
        ]);

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $total = $this->parseNumber($this->cell($row, $map['total'] ?? null));
            if ($total <= 0) {
                $qty = $this->parseNumber($this->cell($row, $map['qty'] ?? null));
                $price = $this->parseNumber($this->cell($row, $map['price'] ?? null));
                $total = round($qty * $price, 2);
            }
            if ($total <= 0) {
                continue;
            }
            $item = $this->cell($row, $map['item'] ?? null) ?: 'Material';
            $this->counts['materials_expenses']++;
            $this->counts['expenses']++;
            if ($dryRun) {
                continue;
            }
            $dual = DualCurrency::legs(DualCurrency::USD, $total);
            Expense::query()->create([
                'project_id' => $project->id,
                'vault_id' => $this->zhakoVault()->id,
                'category' => Expense::CATEGORY_MATERIALS,
                'amount_iqd' => $dual['amount_iqd'],
                'amount_usd' => $dual['amount_usd'],
                'exchange_rate' => 0,
                'currency' => DualCurrency::USD,
                'expense_date' => now()->toDateString(),
                'description' => 'PARYA: '.$item,
                'approval_status' => Expense::STATUS_PENDING,
                'payment_method' => Expense::PAYMENT_CASH,
            ]);
        }
    }

    /** @param list<list<string>> $rows */
    protected function importEstimation(array $rows, bool $dryRun): void
    {
        if ($rows === []) {
            return;
        }

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);
        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $villa = $this->cell($row, 0);
            $desc = $this->cell($row, 1) ?: 'Estimation';
            [$amount, $currency] = $this->parseMoneyToken($this->cell($row, 2));
            $person = $this->cell($row, 3);
            if ($person !== '' && ! $this->isXoman($person)) {
                $this->ensurePerson($person, $dryRun);
            }
            if ($amount <= 0) {
                continue;
            }
            $this->counts['estimations']++;
            if ($dryRun) {
                continue;
            }
            // Store as project note expense (job costing marker) — pending, not vault depleting until approved.
            $dual = DualCurrency::legs($currency, $amount);
            Expense::query()->create([
                'project_id' => $project->id,
                'vault_id' => $this->zhakoVault()->id,
                'category' => Expense::CATEGORY_LABOR,
                'amount_iqd' => $dual['amount_iqd'],
                'amount_usd' => $dual['amount_usd'],
                'exchange_rate' => 0,
                'currency' => $dual['currency'],
                'expense_date' => now()->toDateString(),
                'description' => trim("ESTIMATION villa {$villa}: {$desc}".($person ? " ({$person})" : '')),
                'approval_status' => Expense::STATUS_PENDING,
                'payment_method' => Expense::PAYMENT_OTHER,
            ]);
            $this->counts['expenses']++;
        }
    }

    /** @param list<list<string>> $rows */
    protected function importSummary(array $rows, bool $dryRun): void
    {
        if ($rows === []) {
            return;
        }

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);
        $vault = $dryRun ? null : $this->zhakoVault();

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $client = $this->cell($row, 0) ?: 'Client';
            $status = $this->norm($this->cell($row, 1));
            [$amount, $currency] = $this->parseMoneyToken($this->cell($row, 2));
            if ($amount <= 0) {
                continue;
            }

            $received = str_contains($status, 'received') && ! str_contains($status, 'not');
            if (! $received) {
                // Receivable / not received — skip money-in; count as warning.
                $this->warnings[] = "Summery not received skipped: {$client}";
                continue;
            }

            $this->counts['client_advances']++;
            if ($dryRun) {
                continue;
            }

            $dual = DualCurrency::legs($currency, $amount);
            $txn = Transaction::query()->create([
                'vault_id' => $vault->id,
                'project_id' => $project->id,
                'type' => Transaction::TYPE_MONEY_RECEIVED,
                'direction' => 'in',
                'occurred_on' => now()->toDateString(),
                'amount_usd' => $dual['amount_usd'],
                'amount_iqd' => $dual['amount_iqd'],
                'exchange_rate' => 0,
                'description' => 'Client advance: '.$client,
                'reference_code' => 'import:summary',
            ]);

            $advance = ClientAdvance::query()->create([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'client_name' => $client,
                'amount_usd' => $dual['amount_usd'],
                'amount_iqd' => $dual['amount_iqd'],
                'currency' => $dual['currency'],
                'received_on' => now()->toDateString(),
                'reference' => 'Summery import',
                'transaction_id' => $txn->id,
            ]);

            $held = $this->retention->clientAdvanceRetention(
                (float) $dual['amount_usd'],
                (float) $dual['amount_iqd'],
                now(),
            );
            if ($held['retention_usd'] > 0 || $held['retention_iqd'] > 0) {
                ClientRetentionHold::query()->create([
                    'client_advance_id' => $advance->id,
                    'project_id' => $project->id,
                    'vault_id' => $vault->id,
                    'amount_usd' => $held['retention_usd'],
                    'amount_iqd' => $held['retention_iqd'],
                    'hold_pct' => $held['hold_pct'],
                    'maturity_days' => $held['maturity_days'],
                    'hold_start' => $held['hold_start'],
                    'maturity_date' => $held['maturity_date'],
                    'status' => ClientRetentionHold::STATUS_HOLDING,
                ]);
            }
        }
    }

    /** @param list<list<string>> $rows */
    protected function importHisabatLegacy(array $rows, bool $dryRun): void
    {
        // Abandoned sheet — count only; do not build product UX around it.
        $dataRows = 0;
        foreach (array_slice($rows, 1) as $row) {
            if (! $this->rowEmpty($row)) {
                $dataRows++;
            }
        }
        $this->counts['legacy_hisabat_skipped_rows'] = $dataRows;
        if ($dataRows > 0) {
            $this->warnings[] = "HISABAT treated as legacy only ({$dataRows} rows not imported into live workflows).";
        }
        unset($dryRun);
    }

    protected function ensurePerson(string $name, bool $dryRun): ?Worker
    {
        $name = trim($name);
        if ($name === '' || $this->isXoman($name)) {
            return null;
        }
        $key = $this->nameKey($name);
        if (isset($this->peopleByKey[$key])) {
            return $this->peopleByKey[$key];
        }

        $this->counts['people']++;
        if ($dryRun) {
            // Placeholder so counts de-dupe within dry-run.
            $stub = new Worker(['name' => $name, 'labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED]);
            $this->peopleByKey[$key] = $stub;

            return $stub;
        }

        $person = Worker::query()->firstOrCreate(
            ['name' => $name],
            ['labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED],
        );

        // Never guess labor_kind — force unclassified if somehow classified blank.
        if ($person->labor_kind === null || $person->labor_kind === '') {
            $person->forceFill(['labor_kind' => Worker::LABOR_KIND_UNCLASSIFIED])->save();
        }

        $this->peopleByKey[$key] = $person;

        return $person;
    }

    protected function ensureProject(string $name, string $status = Project::STATUS_ACTIVE): Project
    {
        $key = $this->nameKey($name);
        if (isset($this->projectsByKey[$key])) {
            return $this->projectsByKey[$key];
        }

        $project = Project::query()->firstOrCreate(
            ['name' => $name],
            [
                'status' => $status,
                'client' => 'Mayorca / Zhako',
                'location' => $name,
            ],
        );

        if (! isset($this->projectsByKey[$key])) {
            $this->counts['projects']++;
        }
        $this->projectsByKey[$key] = $project;

        return $project;
    }

    protected function spatialCategory(string $sheetName): string
    {
        $n = $this->norm($sheetName);
        if (str_contains($n, 'laminate')) {
            return ApartmentUnit::CATEGORY_LAMINATE;
        }
        if (str_contains($n, 'metxal') || str_contains($n, 'matxal') || str_contains($sheetName, 'مەتخەل')) {
            return ApartmentUnit::CATEGORY_METXAL;
        }
        if (str_contains($n, 'packet')) {
            return ApartmentUnit::CATEGORY_PACKET;
        }
        if (str_contains($n, 'entrance')) {
            return ApartmentUnit::CATEGORY_ENTRANCE;
        }

        return ApartmentUnit::CATEGORY_MDF;
    }

    protected function isXoman(string $value): bool
    {
        $n = $this->norm($value);

        return $n === 'xoman'
            || $n === 'khoman'
            || str_contains($value, 'خۆمان')
            || str_contains($value, 'خومان');
    }

    protected function isMetaHeader(string $label): bool
    {
        $n = $this->norm($label);

        return in_array($n, ['date', 'roj', 'rooj', 'villa', 'note', 'notes', 'description', 'total', 'sum'], true);
    }

    /**
     * @return array{0: float, 1: string}
     */
    protected function parseMoneyToken(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [0.0, DualCurrency::IQD];
        }
        $currency = DualCurrency::IQD;
        if (str_contains($raw, '$') || preg_match('/\busd\b/i', $raw)) {
            $currency = DualCurrency::USD;
        }
        $amount = $this->parseNumber($raw);

        return [$amount, $currency];
    }

    protected function parseNumber(?string $raw): float
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 0.0;
        }
        // Strip currency markers and thousand separators.
        $clean = preg_replace('/[^\d.\-]/', '', str_replace([',', ' '], ['', ''], $raw));
        if ($clean === null || $clean === '' || $clean === '-' || $clean === '.') {
            return 0.0;
        }

        return round((float) $clean, 2);
    }

    protected function parseDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        // Excel serial date
        if (is_numeric($raw) && (float) $raw > 20000 && (float) $raw < 80000) {
            try {
                return Carbon::create(1899, 12, 30)->addDays((int) $raw)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $header
     * @param  array<string, list<string>>  $aliases
     * @return array<string, int|null>
     */
    protected function headerMap(array $header, array $aliases): array
    {
        $map = [];
        foreach ($aliases as $key => $names) {
            $map[$key] = null;
            foreach ($header as $i => $h) {
                foreach ($names as $alias) {
                    if ($h === $alias || str_contains($h, $alias)) {
                        $map[$key] = $i;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /** @param list<string> $row */
    protected function cell(array $row, int|string|null $index): string
    {
        if ($index === null || $index === '') {
            return '';
        }

        return trim((string) ($row[(int) $index] ?? ''));
    }

    /** @param list<string> $row */
    protected function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function norm(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    protected function nameKey(string $name): string
    {
        return $this->norm($name);
    }

    protected function zhakoVault(): Vault
    {
        return Vault::query()->firstOrCreate(
            ['name' => VaultSeeder::NAME],
            ['balance_usd' => 0, 'balance_iqd' => 0],
        );
    }
}
