<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollDashboardController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
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

        $workersQuery = Worker::query()
            ->with('project:id,name')
            ->orderBy('name');

        if ($projectId) {
            $workersQuery->where('project_id', $projectId);
        }

        $workers = $workersQuery->get();

        $rows = $workers->map(function (Worker $worker) use ($from, $to) {
            $calc = $this->payroll->calculate($worker, $from, $to);
            $penalties = round(
                (float) $calc['late_penalty_usd'] + (float) $calc['absence_penalty_usd'],
                2,
            );

            return [
                'worker_id' => $worker->id,
                'name' => $worker->name,
                'role' => $worker->role,
                'project' => $worker->project
                    ? ['id' => $worker->project->id, 'name' => $worker->project->name]
                    : null,
                'days_present' => $calc['days_present'],
                'overtime_hours' => $calc['overtime_hours'],
                'late_minutes' => $calc['late_minutes'],
                'unexcused_absences' => $calc['unexcused_absences'],
                'base_pay_usd' => $calc['base_pay_usd'],
                'overtime_pay_usd' => $calc['overtime_pay_usd'],
                'late_penalty_usd' => $calc['late_penalty_usd'],
                'absence_penalty_usd' => $calc['absence_penalty_usd'],
                'penalties_usd' => $penalties,
                'net_pay_usd' => $calc['net_pay_usd'],
            ];
        })->values();

        $totals = [
            'workers' => $rows->count(),
            'days_present' => (int) $rows->sum('days_present'),
            'overtime_hours' => round((float) $rows->sum('overtime_hours'), 2),
            'penalties_usd' => round((float) $rows->sum('penalties_usd'), 2),
            'net_pay_usd' => round((float) $rows->sum('net_pay_usd'), 2),
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
        ]);
    }
}
