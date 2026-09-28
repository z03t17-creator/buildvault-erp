<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollDashboardController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function show(Request $request): Response
    {
        $this->authorize('viewPayroll', Vault::class);

        $monthInput = (string) $request->query('month', now()->format('Y-m'));
        try {
            $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $projectId = $request->query('project_id');
        $projectId = $projectId !== null && $projectId !== '' ? (int) $projectId : null;

        $rate = $this->exchangeRates->getUsdToIqd();
        $toIqd = static fn (float $usd): float => round($usd * $rate, 0);

        $workersQuery = Worker::query()
            ->with('project:id,name')
            ->orderBy('name');

        if ($projectId) {
            $workersQuery->where('project_id', $projectId);
        }

        $workers = $workersQuery->get();

        $rows = $workers->map(function (Worker $worker) use ($from, $to, $toIqd) {
            $calc = $this->payroll->calculate($worker, $from, $to);
            $penalties = (float) $calc['penalties_usd'];
            $advancesIqd = (float) $calc['advances_iqd'];
            $holdbackUsd = (float) $calc['insurance_holdback_usd'];

            return [
                'worker_id' => $worker->id,
                'name' => $worker->name,
                'role' => $worker->role,
                'project' => $worker->project
                    ? ['id' => $worker->project->id, 'name' => $worker->project->name]
                    : null,
                'overtime_hours' => $calc['overtime_hours'],
                'base_pay_usd' => $calc['base_pay_usd'],
                'overtime_pay_usd' => $calc['overtime_pay_usd'],
                'gross_pay_usd' => $calc['gross_pay_usd'],
                'recorded_penalties_usd' => $calc['recorded_penalties_usd'],
                'recorded_penalties_iqd' => $calc['recorded_penalties_iqd'],
                'penalties_usd' => $penalties,
                'advances_iqd' => $advancesIqd,
                'advances_usd' => $calc['advances_usd'],
                'insurance_holdback_pct' => $calc['insurance_holdback_pct'],
                'insurance_holdback_usd' => $holdbackUsd,
                'net_pay_usd' => $calc['net_pay_usd'],
                'base_pay_iqd' => $toIqd((float) $calc['base_pay_usd']),
                'overtime_pay_iqd' => $toIqd((float) $calc['overtime_pay_usd']),
                'gross_pay_iqd' => $toIqd((float) $calc['gross_pay_usd']),
                'penalties_iqd' => $toIqd($penalties),
                'insurance_holdback_iqd' => $toIqd($holdbackUsd),
                'net_pay_iqd' => $toIqd((float) $calc['net_pay_usd']),
            ];
        })->values();

        $totals = [
            'workers' => $rows->count(),
            'overtime_hours' => round((float) $rows->sum('overtime_hours'), 2),
            'penalties_usd' => round((float) $rows->sum('penalties_usd'), 2),
            'advances_iqd' => (float) $rows->sum('advances_iqd'),
            'insurance_holdback_iqd' => (float) $rows->sum('insurance_holdback_iqd'),
            'net_pay_usd' => round((float) $rows->sum('net_pay_usd'), 2),
            'penalties_iqd' => (float) $rows->sum('penalties_iqd'),
            'gross_pay_iqd' => (float) $rows->sum('gross_pay_iqd'),
            'net_pay_iqd' => (float) $rows->sum('net_pay_iqd'),
            'base_pay_iqd' => (float) $rows->sum('base_pay_iqd'),
            'overtime_pay_iqd' => (float) $rows->sum('overtime_pay_iqd'),
        ];

        return Inertia::render('Dashboards/Payroll', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'projectId' => $projectId,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'rows' => $rows,
            'totals' => $totals,
            'exchangeRate' => $rate,
            'netFormula' => 'base_ot_minus_penalties_insurance_advances',
        ]);
    }
}
