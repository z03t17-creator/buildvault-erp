<?php

namespace App\Services;

use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Penalty;
use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\RetentionHold;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\Worker;
use App\Support\ReportTypes;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Phase 15 — build professional report payloads from real DB rows (IQD).
 */
class ReportService
{
    public function __construct(
        private readonly ProjectFinancialService $projectFinancials,
        private readonly PayrollService $payroll,
        private readonly ExchangeRateService $exchangeRates,
        private readonly VaultLedgerService $ledger,
        private readonly MonthlySettlementService $settlements,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     type: string,
     *     title_key: string,
     *     category: string,
     *     filters: array<string, mixed>,
     *     columns: list<array{key: string, label_key: string, align?: string, money?: bool}>,
     *     rows: list<array<string, mixed>>,
     *     summary: list<array{label_key: string, value: float|int|string, money?: bool}>,
     *     empty: bool
     * }
     */
    public function build(string $type, array $filters = []): array
    {
        if (! ReportTypes::exists($type)) {
            throw new InvalidArgumentException("Unknown report type: {$type}");
        }

        $normalized = $this->normalizeFilters($type, $filters);

        $payload = match ($type) {
            ReportTypes::PROJECT_FINANCIAL => $this->projectFinancial($normalized),
            ReportTypes::INCOME_RECEIVED => $this->incomeReceived($normalized),
            ReportTypes::EXPENSE => $this->expenseReport($normalized),
            ReportTypes::PROFIT_LOSS => $this->profitLoss($normalized),
            ReportTypes::VAULT_LEDGER => $this->vaultLedger($normalized),
            ReportTypes::MONTHLY_PAYROLL => $this->monthlyPayroll($normalized),
            ReportTypes::EMPLOYEE_SALARY => $this->employeeSalary($normalized),
            ReportTypes::PENALTY => $this->penaltyReport($normalized),
            ReportTypes::INSURANCE_RETENTION => $this->insuranceRetention($normalized),
            ReportTypes::ADVANCE => $this->advanceReport($normalized),
            ReportTypes::INVENTORY => $this->inventory(),
            ReportTypes::STOCK_MOVEMENT => $this->stockMovement($normalized),
            ReportTypes::STOCK_VALUATION => $this->stockValuation(),
            ReportTypes::STOCK_IN_OUT => $this->stockInOut($normalized),
            ReportTypes::LOW_STOCK => $this->lowStock(),
            ReportTypes::PROJECT_PROGRESS => $this->projectProgress($normalized),
            ReportTypes::PROJECT_EXPENSES => $this->projectExpenses($normalized),
            ReportTypes::PROJECT_MATERIALS => $this->projectMaterials($normalized),
            ReportTypes::WORKER_PRODUCTION => $this->workerProduction($normalized),
            default => throw new InvalidArgumentException("Unhandled report type: {$type}"),
        };

        return array_merge([
            'type' => $type,
            'title_key' => 'report_'.$type,
            'category' => ReportTypes::get($type)['category'],
            'filters' => $normalized,
        ], $payload);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(string $type, array $filters): array
    {
        $def = ReportTypes::get($type) ?? ['filters' => []];
        $allowed = $def['filters'];
        $out = [];

        if (in_array('month', $allowed, true)) {
            $month = (string) ($filters['month'] ?? now()->format('Y-m'));
            try {
                $out['month'] = Carbon::createFromFormat('Y-m', $month)->format('Y-m');
            } catch (\Throwable) {
                $out['month'] = now()->format('Y-m');
            }
            $m = Carbon::createFromFormat('Y-m', $out['month'])->startOfMonth();
            $out['from'] = $m->toDateString();
            $out['to'] = $m->copy()->endOfMonth()->toDateString();
        } else {
            if (in_array('from', $allowed, true)) {
                $from = (string) ($filters['from'] ?? now()->startOfMonth()->toDateString());
                try {
                    $out['from'] = Carbon::parse($from)->toDateString();
                } catch (\Throwable) {
                    $out['from'] = now()->startOfMonth()->toDateString();
                }
            }
            if (in_array('to', $allowed, true)) {
                $to = (string) ($filters['to'] ?? now()->toDateString());
                try {
                    $out['to'] = Carbon::parse($to)->toDateString();
                } catch (\Throwable) {
                    $out['to'] = now()->toDateString();
                }
            }
        }

        if (in_array('project_id', $allowed, true)) {
            $pid = $filters['project_id'] ?? null;
            $out['project_id'] = $pid !== null && $pid !== '' ? (int) $pid : null;
            if ($out['project_id'] !== null && $out['project_id'] <= 0) {
                $out['project_id'] = null;
            }
        }

        if (in_array('worker_id', $allowed, true)) {
            $wid = $filters['worker_id'] ?? null;
            $out['worker_id'] = $wid !== null && $wid !== '' ? (int) $wid : null;
            if ($out['worker_id'] !== null && $out['worker_id'] <= 0) {
                $out['worker_id'] = null;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function projectFinancial(array $f): array
    {
        $query = Project::query()->orderBy('name');
        if (! empty($f['project_id'])) {
            $query->where('id', $f['project_id']);
        }
        $projects = $query->get();

        $rows = $projects->map(function (Project $project) {
            $s = $this->projectFinancials->summary($project);

            return [
                'project' => $project->name,
                'status' => $project->status,
                'contract_value_iqd' => $s['contract_value_iqd'],
                'money_received_iqd' => $s['money_received_iqd'],
                'project_expenses_iqd' => $s['project_expenses_iqd'],
                'material_cost_iqd' => $s['material_cost_iqd'],
                'payroll_cost_iqd' => $s['payroll_cost_iqd'],
                'other_expenses_iqd' => $s['other_expenses_iqd'],
                'net_position_iqd' => $s['net_position_iqd'],
            ];
        })->values()->all();

        return [
            'columns' => [
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'status', 'label_key' => 'status'],
                ['key' => 'contract_value_iqd', 'label_key' => 'contract_value', 'align' => 'end', 'money' => true],
                ['key' => 'money_received_iqd', 'label_key' => 'money_received', 'align' => 'end', 'money' => true],
                ['key' => 'project_expenses_iqd', 'label_key' => 'project_expenses', 'align' => 'end', 'money' => true],
                ['key' => 'material_cost_iqd', 'label_key' => 'material_cost', 'align' => 'end', 'money' => true],
                ['key' => 'payroll_cost_iqd', 'label_key' => 'payroll_cost', 'align' => 'end', 'money' => true],
                ['key' => 'net_position_iqd', 'label_key' => 'net_position', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'money_received', 'value' => round(array_sum(array_column($rows, 'money_received_iqd')), 2), 'money' => true],
                ['label_key' => 'project_expenses', 'value' => round(array_sum(array_column($rows, 'project_expenses_iqd')), 2), 'money' => true],
                ['label_key' => 'net_position', 'value' => round(array_sum(array_column($rows, 'net_position_iqd')), 2), 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function incomeReceived(array $f): array
    {
        $q = Transaction::query()
            ->with(['project:id,name', 'creator:id,name'])
            ->whereIn('type', Transaction::MONEY_RECEIVED_TYPES)
            ->whereDate('occurred_on', '>=', $f['from'])
            ->whereDate('occurred_on', '<=', $f['to'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }

        $rows = $q->get()->map(fn (Transaction $t) => [
            'date' => optional($t->occurred_on)->toDateString() ?: optional($t->created_at)->toDateString(),
            'type' => $t->type,
            'project' => $t->project?->name ?? '—',
            'reference' => $t->reference_code ?: ($t->description ?: '—'),
            'user' => $t->creator?->name ?? '—',
            'amount_iqd' => round((float) $t->amount_iqd, 2),
        ])->all();

        $total = round(array_sum(array_column($rows, 'amount_iqd')), 2);

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'type', 'label_key' => 'type'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'reference', 'label_key' => 'reference'],
                ['key' => 'user', 'label_key' => 'user'],
                ['key' => 'amount_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'money_received', 'value' => $total, 'money' => true],
                ['label_key' => 'rows', 'value' => count($rows)],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function expenseReport(array $f): array
    {
        $q = Expense::query()
            ->with(['project:id,name', 'creator:id,name'])
            ->whereDate('expense_date', '>=', $f['from'])
            ->whereDate('expense_date', '<=', $f['to'])
            ->orderByDesc('expense_date');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }

        $rows = $q->get()->map(fn (Expense $e) => [
            'date' => optional($e->expense_date)->toDateString(),
            'project' => $e->project?->name ?? '—',
            'category' => $e->category,
            'description' => $e->description ?: '—',
            'status' => $e->approval_status,
            'payment_method' => $e->payment_method,
            'amount_iqd' => round((float) $e->amount_iqd, 2),
        ])->all();

        $approved = array_sum(array_map(
            fn ($r) => $r['status'] === Expense::STATUS_APPROVED ? $r['amount_iqd'] : 0,
            $rows,
        ));

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'category', 'label_key' => 'category'],
                ['key' => 'description', 'label_key' => 'description'],
                ['key' => 'status', 'label_key' => 'status'],
                ['key' => 'amount_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'total_expenses', 'value' => round(array_sum(array_column($rows, 'amount_iqd')), 2), 'money' => true],
                ['label_key' => 'approved_expenses', 'value' => round($approved, 2), 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function profitLoss(array $f): array
    {
        $month = Carbon::parse($f['from'])->format('Y-m');
        $preview = $this->settlements->preview($month, $f['project_id'] ?? null);

        $income = (float) $preview['money_received_iqd'];
        $expenses = (float) $preview['project_expenses_iqd']
            + (float) $preview['payroll_iqd']
            + (float) $preview['employee_advances_iqd']
            + (float) $preview['other_expenses_iqd'];
        $net = round($income - $expenses, 2);

        $rows = [
            ['line' => 'money_received', 'kind' => 'income', 'amount_iqd' => $income],
            ['line' => 'project_expenses', 'kind' => 'expense', 'amount_iqd' => (float) $preview['project_expenses_iqd']],
            ['line' => 'payroll', 'kind' => 'expense', 'amount_iqd' => (float) $preview['payroll_iqd']],
            ['line' => 'employee_advances', 'kind' => 'expense', 'amount_iqd' => (float) $preview['employee_advances_iqd']],
            ['line' => 'other_expenses', 'kind' => 'expense', 'amount_iqd' => (float) $preview['other_expenses_iqd']],
            ['line' => 'profit_loss', 'kind' => 'net', 'amount_iqd' => $net],
        ];

        return [
            'columns' => [
                ['key' => 'line', 'label_key' => 'line'],
                ['key' => 'kind', 'label_key' => 'type'],
                ['key' => 'amount_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'money_received', 'value' => $income, 'money' => true],
                ['label_key' => 'total_expenses', 'value' => round($expenses, 2), 'money' => true],
                ['label_key' => 'profit_loss', 'value' => $net, 'money' => true],
            ],
            'empty' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function vaultLedger(array $f): array
    {
        $listing = $this->ledger->listing(null, [
            'from' => $f['from'] ?? null,
            'to' => $f['to'] ?? null,
            'project_id' => $f['project_id'] ?? null,
            'include_non_cash' => true,
            'per_page' => 500,
        ]);

        // Ledger listing already serializes Transaction models to arrays.
        $tx = collect($listing['transactions']->items());

        $rows = $tx->map(function (array $t) {
            $direction = $t['direction'] ?? 'none';
            if (! empty($t['is_non_cash'])) {
                $direction = 'non_cash';
            }

            return [
                'date' => $t['date'] ?? null,
                'type' => $t['type'] ?? '—',
                'project' => is_array($t['project'] ?? null)
                    ? ($t['project']['name'] ?? '—')
                    : '—',
                'reference' => $t['reference']
                    ?? $t['reference_code']
                    ?? $t['description']
                    ?? '—',
                'direction' => $direction === 'none' ? 'non_cash' : $direction,
                'amount_iqd' => round((float) ($t['amount_iqd'] ?? 0), 2),
            ];
        })->all();

        $balances = $listing['balances'];

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'type', 'label_key' => 'type'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'direction', 'label_key' => 'direction'],
                ['key' => 'reference', 'label_key' => 'reference'],
                ['key' => 'amount_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'vault_balance', 'value' => (float) $balances['current_iqd'], 'money' => true],
                ['label_key' => 'available_balance', 'value' => (float) $balances['available_iqd'], 'money' => true],
                ['label_key' => 'reserved_insurance', 'value' => (float) $balances['reserved_iqd'], 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function monthlyPayroll(array $f): array
    {
        $rate = $this->exchangeRates->getUsdToIqd();
        $toIqd = static fn (float $usd): float => round($usd * $rate, 0);

        $workers = Worker::query()
            ->with('project:id,name')
            ->when(! empty($f['project_id']), fn ($q) => $q->where('project_id', $f['project_id']))
            ->orderBy('name')
            ->get();

        $rows = $workers->map(function (Worker $worker) use ($f, $toIqd) {
            $calc = $this->payroll->calculate($worker, $f['from'], $f['to']);

            return [
                'worker' => $worker->name,
                'role' => $worker->role,
                'project' => $worker->project?->name ?? '—',
                'base_pay_iqd' => $toIqd((float) $calc['base_pay_usd']),
                'overtime_pay_iqd' => $toIqd((float) $calc['overtime_pay_usd']),
                'gross_pay_iqd' => $toIqd((float) $calc['gross_pay_usd']),
                'penalties_iqd' => $toIqd((float) $calc['penalties_usd']),
                'advances_iqd' => (float) $calc['advances_iqd'],
                'insurance_holdback_iqd' => $toIqd((float) $calc['insurance_holdback_usd']),
                'net_pay_iqd' => $toIqd((float) $calc['net_pay_usd']),
            ];
        })->values()->all();

        return [
            'columns' => [
                ['key' => 'worker', 'label_key' => 'worker'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'base_pay_iqd', 'label_key' => 'base_pay', 'align' => 'end', 'money' => true],
                ['key' => 'gross_pay_iqd', 'label_key' => 'gross_pay', 'align' => 'end', 'money' => true],
                ['key' => 'penalties_iqd', 'label_key' => 'penalties', 'align' => 'end', 'money' => true],
                ['key' => 'advances_iqd', 'label_key' => 'advances', 'align' => 'end', 'money' => true],
                ['key' => 'insurance_holdback_iqd', 'label_key' => 'insurance_holdback', 'align' => 'end', 'money' => true],
                ['key' => 'net_pay_iqd', 'label_key' => 'net_pay', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'gross_pay', 'value' => (float) array_sum(array_column($rows, 'gross_pay_iqd')), 'money' => true],
                ['label_key' => 'net_pay', 'value' => (float) array_sum(array_column($rows, 'net_pay_iqd')), 'money' => true],
                ['label_key' => 'workers', 'value' => count($rows)],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function employeeSalary(array $f): array
    {
        $query = Worker::query()->with('project:id,name')->orderBy('name');
        if (! empty($f['worker_id'])) {
            $query->where('id', $f['worker_id']);
        }
        if (! empty($f['project_id'])) {
            $query->where('project_id', $f['project_id']);
        }
        $workers = $query->get();
        $rate = $this->exchangeRates->getUsdToIqd();
        $toIqd = static fn (float $usd): float => round($usd * $rate, 0);

        $rows = $workers->map(function (Worker $worker) use ($f, $toIqd) {
            $calc = $this->payroll->calculate($worker, $f['from'], $f['to']);

            return [
                'worker' => $worker->name,
                'role' => $worker->role,
                'project' => $worker->project?->name ?? '—',
                'phone' => $worker->phone ?: '—',
                'base_pay_iqd' => $toIqd((float) $calc['base_pay_usd']),
                'overtime_hours' => $calc['overtime_hours'],
                'overtime_pay_iqd' => $toIqd((float) $calc['overtime_pay_usd']),
                'penalties_iqd' => $toIqd((float) $calc['penalties_usd']),
                'advances_iqd' => (float) $calc['advances_iqd'],
                'insurance_holdback_iqd' => $toIqd((float) $calc['insurance_holdback_usd']),
                'net_pay_iqd' => $toIqd((float) $calc['net_pay_usd']),
            ];
        })->values()->all();

        return [
            'columns' => [
                ['key' => 'worker', 'label_key' => 'worker'],
                ['key' => 'role', 'label_key' => 'role'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'base_pay_iqd', 'label_key' => 'base_pay', 'align' => 'end', 'money' => true],
                ['key' => 'overtime_pay_iqd', 'label_key' => 'overtime_pay', 'align' => 'end', 'money' => true],
                ['key' => 'penalties_iqd', 'label_key' => 'penalties', 'align' => 'end', 'money' => true],
                ['key' => 'advances_iqd', 'label_key' => 'advances', 'align' => 'end', 'money' => true],
                ['key' => 'net_pay_iqd', 'label_key' => 'net_pay', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'net_pay', 'value' => (float) array_sum(array_column($rows, 'net_pay_iqd')), 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function penaltyReport(array $f): array
    {
        $q = Penalty::query()
            ->with(['worker:id,name', 'project:id,name'])
            ->whereDate('occurred_on', '>=', $f['from'])
            ->whereDate('occurred_on', '<=', $f['to'])
            ->orderByDesc('occurred_on');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }
        if (! empty($f['worker_id'])) {
            $q->where('worker_id', $f['worker_id']);
        }

        $rate = $this->exchangeRates->getUsdToIqd();
        $rows = $q->get()->map(function (Penalty $p) use ($rate) {
            $iqd = $p->amount_iqd !== null
                ? round((float) $p->amount_iqd, 2)
                : round((float) $p->amount_usd * $rate, 0);

            return [
                'date' => optional($p->occurred_on)->toDateString(),
                'worker' => $p->worker?->name ?? '—',
                'project' => $p->project?->name ?? '—',
                'type' => $p->type,
                'status' => $p->status,
                'reason' => $p->reason ?: '—',
                'amount_iqd' => $iqd,
            ];
        })->all();

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'worker', 'label_key' => 'worker'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'type', 'label_key' => 'type'],
                ['key' => 'status', 'label_key' => 'status'],
                ['key' => 'amount_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'penalties', 'value' => round(array_sum(array_column($rows, 'amount_iqd')), 2), 'money' => true],
                ['label_key' => 'rows', 'value' => count($rows)],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function insuranceRetention(array $f): array
    {
        $q = RetentionHold::query()
            ->with(['worker:id,name', 'project:id,name'])
            ->whereDate('hold_start', '>=', $f['from'])
            ->whereDate('hold_start', '<=', $f['to'])
            ->orderByDesc('hold_start');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }
        if (! empty($f['worker_id'])) {
            $q->where('worker_id', $f['worker_id']);
        }

        $rate = $this->exchangeRates->getUsdToIqd();
        $rows = $q->get()->map(fn (RetentionHold $h) => [
            'worker' => $h->worker?->name ?? '—',
            'project' => $h->project?->name ?? '—',
            'hold_start' => optional($h->hold_start)->toDateString(),
            'maturity_date' => optional($h->maturity_date)->toDateString(),
            'status' => $h->status,
            'hold_pct' => $h->hold_pct,
            'amount_iqd' => round((float) $h->amount_usd * $rate, 0),
        ])->all();

        return [
            'columns' => [
                ['key' => 'worker', 'label_key' => 'worker'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'hold_start', 'label_key' => 'hold_start'],
                ['key' => 'maturity_date', 'label_key' => 'maturity_date'],
                ['key' => 'status', 'label_key' => 'status'],
                ['key' => 'amount_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'insurance_held', 'value' => round(array_sum(array_column($rows, 'amount_iqd')), 2), 'money' => true],
                ['label_key' => 'rows', 'value' => count($rows)],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function advanceReport(array $f): array
    {
        $q = EmployeeAdvance::query()
            ->with(['worker:id,name', 'project:id,name'])
            ->whereDate('advanced_on', '>=', $f['from'])
            ->whereDate('advanced_on', '<=', $f['to'])
            ->orderByDesc('advanced_on');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }
        if (! empty($f['worker_id'])) {
            $q->where('worker_id', $f['worker_id']);
        }

        $rows = $q->get()->map(fn (EmployeeAdvance $a) => [
            'date' => optional($a->advanced_on)->toDateString(),
            'worker' => $a->worker?->name ?? '—',
            'project' => $a->project?->name ?? '—',
            'status' => $a->status,
            'repayment_method' => $a->repayment_method,
            'reason' => $a->reason ?: '—',
            'amount_iqd' => round((float) $a->amount_iqd, 2),
            'remaining_iqd' => round((float) $a->remaining_iqd, 2),
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'worker', 'label_key' => 'worker'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'status', 'label_key' => 'status'],
                ['key' => 'amount_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
                ['key' => 'remaining_iqd', 'label_key' => 'remaining', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'advances', 'value' => round(array_sum(array_column($rows, 'amount_iqd')), 2), 'money' => true],
                ['label_key' => 'remaining', 'value' => round(array_sum(array_column($rows, 'remaining_iqd')), 2), 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function inventory(): array
    {
        $rows = StockItem::query()
            ->with('supplier:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (StockItem $i) => [
                'name' => $i->name,
                'sku' => $i->sku ?: '—',
                'category' => $i->category ?: '—',
                'unit' => $i->unit,
                'quantity' => round((float) $i->quantity, 3),
                'min_quantity' => round((float) $i->min_quantity, 3),
                'purchase_price_iqd' => round((float) $i->purchase_price_iqd, 2),
                'stock_value_iqd' => $i->stockValueIqd(),
                'supplier' => $i->supplier?->name ?? '—',
                'location' => $i->location ?: '—',
            ])->all();

        return [
            'columns' => [
                ['key' => 'name', 'label_key' => 'item'],
                ['key' => 'sku', 'label_key' => 'sku'],
                ['key' => 'category', 'label_key' => 'category'],
                ['key' => 'quantity', 'label_key' => 'quantity', 'align' => 'end'],
                ['key' => 'unit', 'label_key' => 'unit'],
                ['key' => 'purchase_price_iqd', 'label_key' => 'unit_price', 'align' => 'end', 'money' => true],
                ['key' => 'stock_value_iqd', 'label_key' => 'stock_value', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'total_items', 'value' => count($rows)],
                ['label_key' => 'stock_value', 'value' => round(array_sum(array_column($rows, 'stock_value_iqd')), 2), 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function stockMovement(array $f): array
    {
        $q = StockMovement::query()
            ->with(['item:id,name,sku,unit', 'project:id,name', 'user:id,name'])
            ->whereDate('moved_on', '>=', $f['from'])
            ->whereDate('moved_on', '<=', $f['to'])
            ->orderByDesc('moved_on')
            ->orderByDesc('id');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }

        $rows = $q->get()->map(fn (StockMovement $m) => [
            'date' => optional($m->moved_on)->toDateString(),
            'type' => $m->type,
            'item' => $m->item?->name ?? '—',
            'sku' => $m->item?->sku ?: '—',
            'project' => $m->project?->name ?? '—',
            'quantity' => round((float) $m->quantity, 3),
            'previous_qty' => round((float) $m->previous_qty, 3),
            'new_qty' => round((float) $m->new_qty, 3),
            'line_value_iqd' => $m->lineValueIqd(),
            'user' => $m->user?->name ?? '—',
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'type', 'label_key' => 'type'],
                ['key' => 'item', 'label_key' => 'item'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'quantity', 'label_key' => 'quantity', 'align' => 'end'],
                ['key' => 'line_value_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'rows', 'value' => count($rows)],
                ['label_key' => 'stock_value', 'value' => round(array_sum(array_column($rows, 'line_value_iqd')), 2), 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function stockValuation(): array
    {
        $rows = StockItem::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (StockItem $i) => [
                'category' => $i->category ?: __('uncategorized'),
                'name' => $i->name,
                'sku' => $i->sku ?: '—',
                'quantity' => round((float) $i->quantity, 3),
                'unit' => $i->unit,
                'purchase_price_iqd' => round((float) $i->purchase_price_iqd, 2),
                'stock_value_iqd' => $i->stockValueIqd(),
            ])->all();

        $byCategory = [];
        foreach ($rows as $row) {
            $cat = $row['category'];
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $row['stock_value_iqd'];
        }

        return [
            'columns' => [
                ['key' => 'category', 'label_key' => 'category'],
                ['key' => 'name', 'label_key' => 'item'],
                ['key' => 'quantity', 'label_key' => 'quantity', 'align' => 'end'],
                ['key' => 'purchase_price_iqd', 'label_key' => 'unit_price', 'align' => 'end', 'money' => true],
                ['key' => 'stock_value_iqd', 'label_key' => 'stock_value', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => array_merge(
                [['label_key' => 'stock_value', 'value' => round(array_sum(array_column($rows, 'stock_value_iqd')), 2), 'money' => true]],
                collect($byCategory)->map(fn ($v, $k) => [
                    'label_key' => $k,
                    'value' => round($v, 2),
                    'money' => true,
                ])->values()->all(),
            ),
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function stockInOut(array $f): array
    {
        $base = StockMovement::query()
            ->whereDate('moved_on', '>=', $f['from'])
            ->whereDate('moved_on', '<=', $f['to']);

        if (! empty($f['project_id'])) {
            $base->where('project_id', $f['project_id']);
        }

        $inQty = round((float) (clone $base)->where('type', StockMovement::TYPE_IN)->sum('quantity'), 3);
        $outQty = round((float) (clone $base)->where('type', StockMovement::TYPE_OUT)->sum('quantity'), 3);
        $inValue = round((float) (clone $base)->where('type', StockMovement::TYPE_IN)
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(purchase_price_iqd, 0)), 0) as total')->value('total'), 2);
        $outValue = round((float) (clone $base)->where('type', StockMovement::TYPE_OUT)
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(purchase_price_iqd, 0)), 0) as total')->value('total'), 2);

        $rows = (clone $base)
            ->with(['item:id,name,sku,unit', 'project:id,name'])
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockMovement $m) => [
                'date' => optional($m->moved_on)->toDateString(),
                'type' => $m->type,
                'item' => $m->item?->name ?? '—',
                'project' => $m->project?->name ?? '—',
                'quantity' => round((float) $m->quantity, 3),
                'line_value_iqd' => $m->lineValueIqd(),
            ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'type', 'label_key' => 'type'],
                ['key' => 'item', 'label_key' => 'item'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'quantity', 'label_key' => 'quantity', 'align' => 'end'],
                ['key' => 'line_value_iqd', 'label_key' => 'amount', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'stock_in_qty', 'value' => $inQty],
                ['label_key' => 'stock_out_qty', 'value' => $outQty],
                ['label_key' => 'stock_in_value', 'value' => $inValue, 'money' => true],
                ['label_key' => 'stock_out_value', 'value' => $outValue, 'money' => true],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function lowStock(): array
    {
        $items = StockItem::query()->orderBy('name')->get();
        $rows = $items
            ->filter(fn (StockItem $i) => $i->isLowStock() || $i->isOutOfStock())
            ->map(fn (StockItem $i) => [
                'name' => $i->name,
                'sku' => $i->sku ?: '—',
                'category' => $i->category ?: '—',
                'quantity' => round((float) $i->quantity, 3),
                'min_quantity' => round((float) $i->min_quantity, 3),
                'status' => $i->isOutOfStock() ? 'out_of_stock' : 'low_stock',
                'stock_value_iqd' => $i->stockValueIqd(),
            ])->values()->all();

        return [
            'columns' => [
                ['key' => 'name', 'label_key' => 'item'],
                ['key' => 'sku', 'label_key' => 'sku'],
                ['key' => 'quantity', 'label_key' => 'quantity', 'align' => 'end'],
                ['key' => 'min_quantity', 'label_key' => 'min_quantity', 'align' => 'end'],
                ['key' => 'status', 'label_key' => 'status'],
                ['key' => 'stock_value_iqd', 'label_key' => 'stock_value', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'low_stock', 'value' => count(array_filter($rows, fn ($r) => $r['status'] === 'low_stock'))],
                ['label_key' => 'out_of_stock', 'value' => count(array_filter($rows, fn ($r) => $r['status'] === 'out_of_stock'))],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function projectProgress(array $f): array
    {
        $q = ProductionRecord::query()
            ->with(['project:id,name', 'worker:id,name'])
            ->whereDate('recorded_on', '>=', $f['from'])
            ->whereDate('recorded_on', '<=', $f['to'])
            ->orderByDesc('recorded_on');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }

        $rows = $q->get()->map(fn (ProductionRecord $r) => [
            'date' => optional($r->recorded_on)->toDateString(),
            'project' => $r->project?->name ?? '—',
            'worker' => $r->worker?->name ?? '—',
            'unit_type' => $r->unit_type,
            'unit_label' => $r->unit_label ?: '—',
            'assigned' => round((float) $r->assigned, 2),
            'completed' => round((float) $r->completed, 2),
            'received' => round((float) $r->received, 2),
            'remaining' => round((float) $r->remaining, 2),
            'progress_pct' => round((float) $r->progress_pct, 1),
        ])->all();

        $assigned = array_sum(array_column($rows, 'assigned'));
        $completed = array_sum(array_column($rows, 'completed'));

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'worker', 'label_key' => 'worker'],
                ['key' => 'unit_type', 'label_key' => 'unit_type'],
                ['key' => 'assigned', 'label_key' => 'assigned', 'align' => 'end'],
                ['key' => 'completed', 'label_key' => 'completed', 'align' => 'end'],
                ['key' => 'remaining', 'label_key' => 'remaining', 'align' => 'end'],
                ['key' => 'progress_pct', 'label_key' => 'progress', 'align' => 'end'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'assigned', 'value' => round($assigned, 2)],
                ['label_key' => 'completed', 'value' => round($completed, 2)],
                ['label_key' => 'progress', 'value' => $assigned > 0 ? round(($completed / $assigned) * 100, 1) : 0],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function projectExpenses(array $f): array
    {
        return $this->expenseReport($f);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function projectMaterials(array $f): array
    {
        $q = StockMovement::query()
            ->where('type', StockMovement::TYPE_OUT)
            ->with(['item:id,name,sku,unit', 'project:id,name', 'tower:id,name', 'floor:id,name'])
            ->whereDate('moved_on', '>=', $f['from'])
            ->whereDate('moved_on', '<=', $f['to'])
            ->orderByDesc('moved_on');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }

        $rows = $q->get()->map(fn (StockMovement $m) => [
            'date' => optional($m->moved_on)->toDateString(),
            'project' => $m->project?->name ?? '—',
            'item' => $m->item?->name ?? '—',
            'tower' => $m->tower?->name ?? '—',
            'floor' => $m->floor?->name ?? '—',
            'quantity' => round((float) $m->quantity, 3),
            'unit_price_iqd' => round((float) ($m->purchase_price_iqd ?? 0), 2),
            'line_value_iqd' => $m->lineValueIqd(),
            'purpose' => $m->purpose ?: '—',
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'item', 'label_key' => 'item'],
                ['key' => 'quantity', 'label_key' => 'quantity', 'align' => 'end'],
                ['key' => 'unit_price_iqd', 'label_key' => 'unit_price', 'align' => 'end', 'money' => true],
                ['key' => 'line_value_iqd', 'label_key' => 'material_cost', 'align' => 'end', 'money' => true],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'material_cost', 'value' => round(array_sum(array_column($rows, 'line_value_iqd')), 2), 'money' => true],
                ['label_key' => 'rows', 'value' => count($rows)],
            ],
            'empty' => $rows === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, summary: list<array<string, mixed>>, empty: bool}
     */
    protected function workerProduction(array $f): array
    {
        $q = ProductionRecord::query()
            ->with(['worker:id,name', 'project:id,name'])
            ->whereDate('recorded_on', '>=', $f['from'])
            ->whereDate('recorded_on', '<=', $f['to'])
            ->orderByDesc('recorded_on');

        if (! empty($f['project_id'])) {
            $q->where('project_id', $f['project_id']);
        }
        if (! empty($f['worker_id'])) {
            $q->where('worker_id', $f['worker_id']);
        }

        $rows = $q->get()->map(fn (ProductionRecord $r) => [
            'date' => optional($r->recorded_on)->toDateString(),
            'worker' => $r->worker?->name ?? '—',
            'project' => $r->project?->name ?? '—',
            'unit_type' => $r->unit_type,
            'unit_label' => $r->unit_label ?: '—',
            'assigned' => round((float) $r->assigned, 2),
            'completed' => round((float) $r->completed, 2),
            'received' => round((float) $r->received, 2),
            'remaining' => round((float) $r->remaining, 2),
            'progress_pct' => round((float) $r->progress_pct, 1),
        ])->all();

        return [
            'columns' => [
                ['key' => 'date', 'label_key' => 'date'],
                ['key' => 'worker', 'label_key' => 'worker'],
                ['key' => 'project', 'label_key' => 'project'],
                ['key' => 'unit_type', 'label_key' => 'unit_type'],
                ['key' => 'completed', 'label_key' => 'completed', 'align' => 'end'],
                ['key' => 'remaining', 'label_key' => 'remaining', 'align' => 'end'],
                ['key' => 'progress_pct', 'label_key' => 'progress', 'align' => 'end'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label_key' => 'completed', 'value' => round(array_sum(array_column($rows, 'completed')), 2)],
                ['label_key' => 'rows', 'value' => count($rows)],
            ],
            'empty' => $rows === [],
        ];
    }
}
