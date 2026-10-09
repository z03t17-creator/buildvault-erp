<?php

namespace App\Http\Controllers;

use App\Http\Requests\Advance\RepayAdvanceRequest;
use App\Http\Requests\Advance\StoreAdvanceRequest;
use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\RetentionHold;
use App\Models\Worker;
use App\Services\EmployeeAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeAdvanceController extends Controller
{
    public function __construct(
        private readonly EmployeeAdvanceService $advances,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', EmployeeAdvance::class);

        $advances = EmployeeAdvance::query()
            ->with(['worker:id,name,labor_kind', 'project:id,name', 'enteredBy:id,name'])
            ->orderByDesc('advanced_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Advances/Index', [
            'advances' => $advances,
            'repaymentMethods' => EmployeeAdvance::REPAYMENT_METHODS,
            'totals' => [
                'amount_usd' => (float) $advances->sum('amount_usd'),
                'amount_iqd' => (float) $advances->sum('amount_iqd'),
                'remaining_usd' => (float) $advances->sum('remaining_usd'),
                'remaining_iqd' => (float) $advances->sum('remaining_iqd'),
                'open' => (int) $advances->where('status', EmployeeAdvance::STATUS_OPEN)->count(),
                'repaid' => (int) $advances->where('status', EmployeeAdvance::STATUS_REPAID)->count(),
            ],
            'staffHolds' => [
                'holding' => RetentionHold::query()
                    ->where('layer', RetentionHold::LAYER_STAFF)
                    ->where('status', RetentionHold::STATUS_HOLDING)
                    ->count(),
                'matured' => RetentionHold::query()
                    ->where('layer', RetentionHold::LAYER_STAFF)
                    ->where('status', RetentionHold::STATUS_MATURED)
                    ->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', EmployeeAdvance::class);

        return Inertia::render('Advances/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id', 'labor_kind']),
            'repaymentMethods' => EmployeeAdvance::REPAYMENT_METHODS,
            'currencies' => \App\Support\DualCurrency::CURRENCIES,
            'defaults' => [
                'advanced_on' => now()->toDateString(),
                'repayment_method' => EmployeeAdvance::REPAY_PAYROLL,
                'currency' => 'IQD',
            ],
        ]);
    }

    public function store(StoreAdvanceRequest $request): RedirectResponse
    {
        $this->authorize('create', EmployeeAdvance::class);

        try {
            $advance = $this->advances->create(
                $request->validated(),
                Auth::user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('advances.show', $advance)
            ->with('success', __('Advance recorded.'));
    }

    public function show(EmployeeAdvance $advance): Response
    {
        $this->authorize('view', $advance);

        $advance->load(['worker:id,name,labor_kind,project_id', 'project:id,name', 'enteredBy:id,name']);

        return Inertia::render('Advances/Show', [
            'advance' => $advance,
            'repaymentMethods' => EmployeeAdvance::REPAYMENT_METHODS,
        ]);
    }

    public function repay(RepayAdvanceRequest $request, EmployeeAdvance $advance): RedirectResponse
    {
        $this->authorize('repay', $advance);

        try {
            $this->advances->repay($advance, $request->validated('amount'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', __('Repayment recorded.'));
    }

    public function cancel(EmployeeAdvance $advance): RedirectResponse
    {
        $this->authorize('cancel', $advance);

        try {
            $this->advances->cancel($advance);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Advance cancelled.'));
    }
}
