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
use App\Models\Staff;
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

    /** @var array<string, Staff> */
    protected array $staffByKey = [];

    /** @var array<string, Project> */
    protected array $projectsByKey = [];

    /** @var array<string, int> */
    protected array $counts = [];

    /** @var list<string> */
    protected array $warnings = [];

    /** Spatial rows to import from OK_spatial_production (slice). */
    protected int $spatialImportLimit = 180;

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
        $this->staffByKey = [];
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
            'staff' => 0,
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
            foreach (Staff::query()->get() as $person) {
                $this->staffByKey[$this->nameKey($person->name)] = $person;
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
                    'ok_expenses' => $this->importOkExpenses($sheet['rows'], $dryRun),
                    'ok_penalties' => $this->importOkPenalties($sheet['rows'], $dryRun),
                    'ok_vault' => $this->importOkVault($sheet['rows'], $dryRun),
                    'ok_materials' => $this->importOkMaterials($sheet['rows'], $dryRun),
                    'ok_client_receipts' => $this->importOkClientReceipts($sheet['rows'], $dryRun),
                    'ok_spatial' => $this->importOkSpatial($sheet['rows'], $dryRun),
                    'ok_people' => $this->importOkPeopleCandidates($sheet['rows'], $dryRun),
                    'skip' => null,
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

        // Understanding-review workbook (OK_* / NEED_* sheets).
        if (str_starts_with($n, 'ok_expenses')) {
            return 'ok_expenses';
        }
        if (str_starts_with($n, 'ok_penalties')) {
            return 'ok_penalties';
        }
        if (str_starts_with($n, 'ok_vault')) {
            return 'ok_vault';
        }
        if (str_starts_with($n, 'ok_materials') || str_starts_with($n, 'ok_parya')) {
            return 'ok_materials';
        }
        if (str_starts_with($n, 'ok_client')) {
            return 'ok_client_receipts';
        }
        if (str_starts_with($n, 'ok_spatial')) {
            return 'ok_spatial';
        }
        if (str_starts_with($n, 'ok_people')) {
            return 'ok_people';
        }
        if (
            str_starts_with($n, 'ok_')
            || str_starts_with($n, 'need_')
            || str_starts_with($n, '00_')
            || str_starts_with($n, '01_')
            || str_starts_with($n, '02_')
            || str_starts_with($n, '03_')
        ) {
            return 'skip';
        }

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
            $this->ensureStaff($name, $dryRun, Staff::PAY_DAILY);
            $this->counts['penalties']++;
            if ($dryRun) {
                continue;
            }
            $person = $this->ensureStaff($name, false, Staff::PAY_DAILY);
            $dual = DualCurrency::legs($currency, $amount);
            Penalty::query()->create([
                'staff_id' => $person->id,
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
    protected function importOkExpenses(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'project' => ['project_guess', 'project'],
            'date' => ['date'],
            'description' => ['description', 'desc'],
            'amount' => ['amount', 'price'],
            'currency' => ['currency'],
            'action' => ['your_action', 'action'],
        ]);

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row) || $this->isIgnoredAction($this->cell($row, $map['action'] ?? null))) {
                continue;
            }
            $amount = $this->parseNumber($this->cell($row, $map['amount'] ?? null));
            if ($amount <= 0) {
                continue;
            }
            $currency = strtoupper($this->cell($row, $map['currency'] ?? null) ?: DualCurrency::IQD);
            if (! in_array($currency, DualCurrency::CURRENCIES, true)) {
                $currency = DualCurrency::IQD;
            }
            $projectName = $this->cell($row, $map['project'] ?? null) ?: 'Mayorca Zhako';
            $projectName = preg_replace('/\s*\/\s*Rayan daily$/i', '', $projectName) ?: 'Mayorca Zhako';
            $desc = $this->cell($row, $map['description'] ?? null) ?: 'Site expense';
            $date = $this->parseDate($this->cell($row, $map['date'] ?? null)) ?? now()->toDateString();

            $this->counts['expenses']++;
            if ($dryRun) {
                continue;
            }

            $project = $this->ensureProject($projectName, Project::STATUS_ACTIVE);
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
    protected function importOkPenalties(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'date' => ['date'],
            'name' => ['person_name', 'name', 'staff'],
            'reason' => ['reason'],
            'amount' => ['amount', 'price'],
            'currency' => ['currency'],
            'action' => ['your_action', 'action'],
        ]);

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row) || $this->isIgnoredAction($this->cell($row, $map['action'] ?? null))) {
                continue;
            }
            $name = $this->cell($row, $map['name'] ?? null);
            if ($name === '' || $this->isXoman($name)) {
                continue;
            }
            $amount = $this->parseNumber($this->cell($row, $map['amount'] ?? null));
            if ($amount <= 0) {
                continue;
            }
            $currency = strtoupper($this->cell($row, $map['currency'] ?? null) ?: DualCurrency::IQD);
            if (! in_array($currency, DualCurrency::CURRENCIES, true)) {
                [$amount, $currency] = $this->parseMoneyToken($this->cell($row, $map['amount'] ?? null));
            }
            $this->ensureStaff($name, $dryRun, Staff::PAY_DAILY);
            $this->counts['penalties']++;
            if ($dryRun) {
                continue;
            }
            $person = $this->ensureStaff($name, false, Staff::PAY_DAILY);
            $dual = DualCurrency::legs($currency, $amount);
            Penalty::query()->create([
                'staff_id' => $person->id,
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
    protected function importOkVault(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'date' => ['date'],
            'note' => ['note', 'type_of_work', 'description'],
            'in_usd' => ['pays_in_usd', 'in usd', 'in$'],
            'out_usd' => ['pays_out_usd', 'out usd', 'out$'],
            'in_iqd' => ['pays_in_iqd', 'in iqd'],
            'out_iqd' => ['pays_out_iqd', 'out iqd'],
            'action' => ['your_action', 'action'],
        ]);

        $vault = $dryRun ? null : $this->zhakoVault();

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row) || $this->isIgnoredAction($this->cell($row, $map['action'] ?? null))) {
                continue;
            }
            $date = $this->parseDate($this->cell($row, $map['date'] ?? null));
            $desc = $this->cell($row, $map['note'] ?? null) ?: 'Qasa entry';
            $legs = [
                ['direction' => 'in', 'currency' => DualCurrency::USD, 'amount' => $this->parseNumber($this->cell($row, $map['in_usd'] ?? null))],
                ['direction' => 'out', 'currency' => DualCurrency::USD, 'amount' => $this->parseNumber($this->cell($row, $map['out_usd'] ?? null))],
                ['direction' => 'in', 'currency' => DualCurrency::IQD, 'amount' => $this->parseNumber($this->cell($row, $map['in_iqd'] ?? null))],
                ['direction' => 'out', 'currency' => DualCurrency::IQD, 'amount' => $this->parseNumber($this->cell($row, $map['out_iqd'] ?? null))],
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
                Transaction::query()->create([
                    'vault_id' => $vault->id,
                    'type' => $leg['direction'] === 'in' ? Transaction::TYPE_DEPOSIT : Transaction::TYPE_WITHDRAWAL,
                    'direction' => $leg['direction'],
                    'occurred_on' => $date ?? now()->toDateString(),
                    'amount_usd' => $dual['amount_usd'],
                    'amount_iqd' => $dual['amount_iqd'],
                    'exchange_rate' => 0,
                    'description' => $desc,
                    'reference_code' => 'import:ok_vault_qasa',
                ]);
            }
        }
    }

    /** @param list<list<string>> $rows */
    protected function importOkMaterials(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'date' => ['date'],
            'subject' => ['subject', 'item', 'description'],
            'qty' => ['quantity', 'qty'],
            'unit_price' => ['unit_price', 'price'],
            'total' => ['total'],
            'currency' => ['currency'],
            'guess' => ['table_guess', 'guess'],
        ]);

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $guess = $this->norm($this->cell($row, $map['guess'] ?? null));
            if (str_contains($guess, 'summary')) {
                continue;
            }
            $subject = $this->cell($row, $map['subject'] ?? null);
            if ($subject === '' || str_contains($this->norm($subject), 'rest')) {
                continue;
            }
            $qty = $this->parseNumber($this->cell($row, $map['qty'] ?? null));
            $unit = $this->parseNumber($this->cell($row, $map['unit_price'] ?? null));
            $total = $this->parseNumber($this->cell($row, $map['total'] ?? null));
            if ($total <= 0 && $qty > 0 && $unit > 0) {
                $total = round($qty * $unit, 2);
            }
            if ($total <= 0) {
                continue;
            }
            // Review sheet marks PARYA as USD purchases; fall back to column currency.
            $currency = strtoupper($this->cell($row, $map['currency'] ?? null) ?: DualCurrency::USD);
            if ($currency === DualCurrency::IQD && $qty > 0 && $unit > 0 && $unit < 100) {
                $currency = DualCurrency::USD;
                $total = round($qty * $unit, 2);
            }
            if (! in_array($currency, DualCurrency::CURRENCIES, true)) {
                $currency = DualCurrency::USD;
            }

            $this->counts['materials_expenses']++;
            $this->counts['expenses']++;
            if ($dryRun) {
                continue;
            }
            $dual = DualCurrency::legs($currency, $total);
            Expense::query()->create([
                'project_id' => $project->id,
                'vault_id' => $this->zhakoVault()->id,
                'category' => Expense::CATEGORY_MATERIALS,
                'amount_iqd' => $dual['amount_iqd'],
                'amount_usd' => $dual['amount_usd'],
                'exchange_rate' => 0,
                'currency' => $dual['currency'],
                'expense_date' => $this->parseDate($this->cell($row, $map['date'] ?? null)) ?? now()->toDateString(),
                'description' => 'PARYA: '.$subject.($qty > 0 ? " × {$qty}" : ''),
                'approval_status' => Expense::STATUS_PENDING,
                'payment_method' => Expense::PAYMENT_CASH,
            ]);
        }
    }

    /** @param list<list<string>> $rows */
    protected function importOkClientReceipts(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'date' => ['date'],
            'reference' => ['reference', 'ref'],
            'note' => ['note'],
            'amount' => ['amount'],
            'currency' => ['currency'],
            'status' => ['status'],
            'action' => ['your_action', 'action'],
        ]);

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);
        $vault = $dryRun ? null : $this->zhakoVault();

        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row) || $this->isIgnoredAction($this->cell($row, $map['action'] ?? null))) {
                continue;
            }
            $amount = $this->parseNumber($this->cell($row, $map['amount'] ?? null));
            if ($amount <= 0) {
                continue;
            }
            $status = $this->norm($this->cell($row, $map['status'] ?? null));
            // Workbooks often typo "Recieved".
            $received = (str_contains($status, 'receiv') || str_contains($status, 'reciev'))
                && ! str_contains($status, 'not');
            if (! $received) {
                $this->warnings[] = 'OK client receipt not received skipped: '.$this->cell($row, $map['reference'] ?? null);
                continue;
            }
            // Summery amounts are client USD even when the review column says IQD.
            $currency = DualCurrency::USD;
            $ref = $this->cell($row, $map['reference'] ?? null) ?: 'Client';
            $note = $this->cell($row, $map['note'] ?? null);
            $client = trim($ref.($note !== '' ? ' '.$note : ''));
            $date = $this->parseDate($this->cell($row, $map['date'] ?? null)) ?? now()->toDateString();

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
                'occurred_on' => $date,
                'amount_usd' => $dual['amount_usd'],
                'amount_iqd' => $dual['amount_iqd'],
                'exchange_rate' => 0,
                'description' => 'Client advance: '.$client,
                'reference_code' => 'import:ok_client_receipts',
            ]);

            $advance = ClientAdvance::query()->create([
                'project_id' => $project->id,
                'vault_id' => $vault->id,
                'client_name' => $client,
                'amount_usd' => $dual['amount_usd'],
                'amount_iqd' => $dual['amount_iqd'],
                'currency' => $dual['currency'],
                'received_on' => $date,
                'reference' => $ref,
                'transaction_id' => $txn->id,
            ]);

            $held = $this->retention->clientAdvanceRetention(
                (float) $dual['amount_usd'],
                (float) $dual['amount_iqd'],
                Carbon::parse($date),
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
    protected function importOkSpatial(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'trade' => ['trade'],
            'block' => ['building_block', 'block'],
            'floor' => ['floor'],
            'apartment' => ['apartment', 'unit'],
            'worker' => ['worker_or_crew', 'worker'],
            'qty' => ['qty_if_any', 'qty'],
            'action' => ['your_action', 'action'],
        ]);

        $project = $dryRun ? null : $this->ensureProject('Mayorca Zhako', Project::STATUS_ACTIVE);
        $imported = 0;

        foreach (array_slice($rows, 1) as $row) {
            if ($imported >= $this->spatialImportLimit) {
                $this->warnings[] = "OK spatial slice capped at {$this->spatialImportLimit} cells (B1-first).";
                break;
            }
            if ($this->rowEmpty($row) || $this->isIgnoredAction($this->cell($row, $map['action'] ?? null))) {
                continue;
            }

            $blockCode = strtoupper($this->cell($row, $map['block'] ?? null) ?: 'B1');
            // Prefer B1 for the first slice; skip other blocks until the cap is raised.
            if ($blockCode !== 'B1') {
                continue;
            }

            $floor = (int) preg_replace('/[^0-9\-]/', '', $this->cell($row, $map['floor'] ?? null));
            $unitLabel = $this->cell($row, $map['apartment'] ?? null);
            $workerRaw = $this->cell($row, $map['worker'] ?? null);
            if ($floor <= 0 || $unitLabel === '' || $workerRaw === '') {
                continue;
            }

            $trade = $this->cell($row, $map['trade'] ?? null) ?: 'MDF';
            $category = $this->spatialCategory($trade);
            $isCrew = $this->isXoman($workerRaw);
            $workerName = $this->extractWorkerName($workerRaw);

            if (! $isCrew && $workerName !== '') {
                $this->ensurePerson($workerName, $dryRun);
                $this->ensureStaff($workerName, $dryRun, Staff::PAY_UNIT);
            }

            $this->counts['spatial_units']++;
            $imported++;
            if ($dryRun) {
                continue;
            }

            $block = BuildingBlock::query()->firstOrCreate(
                ['project_id' => $project->id, 'code' => $blockCode],
                ['name' => 'Block '.$blockCode],
            );

            $workerId = null;
            if (! $isCrew && $workerName !== '') {
                $workerId = $this->ensurePerson($workerName, false)?->id;
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
                    'notes' => $workerRaw,
                ],
            );
        }
    }

    /** @param list<list<string>> $rows */
    protected function importOkPeopleCandidates(array $rows, bool $dryRun): void
    {
        if (count($rows) < 2) {
            return;
        }

        $header = array_map(fn ($h) => $this->norm((string) $h), $rows[0]);
        $map = $this->headerMap($header, [
            'name' => ['name'],
            'classify' => ['your_classify', 'classify'],
            'count' => ['seen_count', 'count'],
        ]);

        $created = 0;
        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $name = $this->cell($row, $map['name'] ?? null);
            if ($name === '' || $this->isXoman($name) || $this->norm($name) === 'karmand') {
                continue;
            }
            $classify = $this->norm($this->cell($row, $map['classify'] ?? null));
            $seen = (int) $this->parseNumber($this->cell($row, $map['count'] ?? null));

            // Auto-import frequent names as unit staff when review left classify blank.
            $asStaff = str_contains($classify, 'staff')
                || str_contains($classify, 'worker')
                || ($classify === '' || str_contains($classify, 'need')) && $seen >= 10;

            if (! $asStaff || str_contains($classify, 'ignore') || str_contains($classify, 'vendor')) {
                continue;
            }

            $payModel = str_contains($classify, 'staff') && ! str_contains($classify, 'worker')
                ? Staff::PAY_DAILY
                : Staff::PAY_UNIT;

            $this->ensureStaff($name, $dryRun, $payModel);
            $this->ensurePerson($name, $dryRun);
            $created++;
            if ($created >= 12) {
                break;
            }
        }
    }

    protected function isIgnoredAction(string $action): bool
    {
        $n = $this->norm($action);

        return str_contains($n, 'ignore') || str_contains($n, 'skip') || str_contains($n, 'delete');
    }

    protected function extractWorkerName(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || $this->isXoman($raw)) {
            return '';
        }
        // Skip combo cells like "hunar+1 aland" / "aland+1xoman".
        if (str_contains($raw, '+')) {
            return '';
        }
        // Cells like "5 shwan", "2 xoman", "5mhmad"
        if (preg_match('/^\d+\s*(.+)$/u', $raw, $m)) {
            $raw = trim($m[1]);
        } elseif (preg_match('/^(\d+)([a-zA-Z\x{0600}-\x{06FF}].*)$/u', $raw, $m)) {
            $raw = trim($m[2]);
        }
        if ($this->isXoman($raw) || $raw === '') {
            return '';
        }

        return $raw;
    }

    protected function ensureStaff(string $name, bool $dryRun, string $payModel = Staff::PAY_UNIT): ?Staff
    {
        $name = trim($name);
        if ($name === '' || $this->isXoman($name)) {
            return null;
        }
        $key = $this->nameKey($name);
        if (isset($this->staffByKey[$key])) {
            return $this->staffByKey[$key];
        }

        // Match existing staff case-insensitively (e.g. hogr already in roster).
        foreach ($this->staffByKey as $existingKey => $existing) {
            if ($existingKey === $key) {
                return $existing;
            }
        }

        if ($dryRun) {
            $this->counts['staff']++;
            $stub = new Staff([
                'name' => $name,
                'pay_model' => $payModel,
                'kind' => Staff::kindFromPayModel($payModel),
            ]);
            $this->staffByKey[$key] = $stub;

            return $stub;
        }

        $existing = Staff::query()
            ->get()
            ->first(fn (Staff $person) => $this->nameKey($person->name) === $key);

        if ($existing) {
            $this->staffByKey[$key] = $existing;

            return $existing;
        }

        $attrs = [
            'name' => $name,
            'pay_model' => $payModel,
            'kind' => Staff::kindFromPayModel($payModel),
            'role' => $payModel === Staff::PAY_UNIT ? 'دەرگاچیی' : null,
        ];

        $person = Staff::query()->create($attrs);
        $this->counts['staff']++;
        $this->staffByKey[$key] = $person;

        return $person;
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
