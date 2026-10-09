<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payout\RejectPayoutRequest;
use App\Http\Requests\Payout\StorePayoutRequest;
use App\Models\Floor;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\LiquidityService;
use App\Services\PayrollService;
use App\Services\PayoutService;
use App\Support\DualCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly PayrollService $payroll,
        private readonly LiquidityService $liquidity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payout::class);

        $status = $request->string('status')->toString();
        $category = $request->string('category')->toString();

        if ($status !== '' && ! in_array($status, Payout::STATUSES, true)) {
            $status = '';
        }
        if ($category !== '' && ! in_array($category, Payout::CATEGORIES, true)) {
            $category = '';
        }

        $query = Payout::query()
            ->with(['project:id,name', 'worker:id,name', 'vault:id,name'])
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($category !== '') {
            $query->where('category', $category);
        }

        $payouts = $query->get();

        $statusScoped = Payout::query();
        if ($category !== '') {
            $statusScoped->where('category', $category);
        }
        $statusRows = (clone $statusScoped)->get(['status']);
        $statusCounts = [
            'all' => $statusRows->count(),
            'pending' => $statusRows->where('status', Payout::STATUS_PENDING)->count(),
            'held' => $statusRows->where('status', Payout::STATUS_HELD)->count(),
            'approved' => $statusRows->where('status', Payout::STATUS_APPROVED)->count(),
            'reconciled' => $statusRows->where('status', Payout::STATUS_RECONCILED)->count(),
            'rejected' => $statusRows->where('status', Payout::STATUS_REJECTED)->count(),
        ];

        $categoryScoped = Payout::query();
        if ($status !== '') {
            $categoryScoped->where('status', $status);
        }
        $categoryRows = (clone $categoryScoped)->get(['category']);
        $categoryCounts = collect(Payout::CATEGORIES)
            ->mapWithKeys(fn (string $c) => [$c => $categoryRows->where('category', $c)->count()])
            ->all();

        $open = $payouts->whereIn('status', [
            Payout::STATUS_PENDING,
            Payout::STATUS_HELD,
            Payout::STATUS_APPROVED,
        ]);
        $pending = $payouts->where('status', Payout::STATUS_PENDING);

        $overview = [
            'count' => $payouts->count(),
            'pending' => $pending->count(),
            'held' => $payouts->where('status', Payout::STATUS_HELD)->count(),
            'approved' => $payouts->where('status', Payout::STATUS_APPROVED)->count(),
            'reconciled' => $payouts->where('status', Payout::STATUS_RECONCILED)->count(),
            'rejected' => $payouts->where('status', Payout::STATUS_REJECTED)->count(),
            'open_usd' => round((float) $open->sum(fn (Payout $p) => (float) $p->amount_usd), 2),
            'open_iqd' => round((float) $open->sum(fn (Payout $p) => (float) $p->amount_iqd), 0),
            'pending_usd' => round((float) $pending->sum(fn (Payout $p) => (float) $p->amount_usd), 2),
            'pending_iqd' => round((float) $pending->sum(fn (Payout $p) => (float) $p->amount_iqd), 0),
        ];

        try {
            $availableCash = $this->liquidity->dualSnapshot();
        } catch (InvalidArgumentException) {
            $availableCash = [
                'balance_usd' => 0,
                'balance_iqd' => 0,
                'available_usd' => 0,
                'available_iqd' => 0,
                'pending_usd' => 0,
                'pending_iqd' => 0,
                'reserved_usd' => 0,
                'reserved_iqd' => 0,
            ];
        }

        $ability = [
            'available_usd' => (float) $availableCash['available_usd'],
            'available_iqd' => (float) $availableCash['available_iqd'],
            'pending_commitments_usd' => (float) $availableCash['pending_usd'],
            'pending_commitments_iqd' => (float) $availableCash['pending_iqd'],
            'blocks_usd' => (float) $availableCash['available_usd'] <= 0
                && $overview['pending_usd'] > 0,
            'blocks_iqd' => (float) $availableCash['available_iqd'] <= 0
                && $overview['pending_iqd'] > 0,
        ];

        return Inertia::render('Payouts/Index', [
            'payouts' => $payouts,
            'statuses' => Payout::STATUSES,
            'categories' => Payout::CATEGORIES,
            'filters' => [
                'status' => $status !== '' ? $status : null,
                'category' => $category !== '' ? $category : null,
            ],
            'statusCounts' => $statusCounts,
            'categoryCounts' => $categoryCounts,
            'overview' => $overview,
            'availableCash' => $availableCash,
            'ability' => $ability,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Payout::class);

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $payrollSuggestions = Worker::query()
            ->where('labor_kind', Worker::LABOR_KIND_WORKER)
            ->orderBy('name')
            ->get(['id', 'name', 'project_id', 'daily_rate_usd', 'overtime_rate_usd', 'monthly_salary_usd', 'monthly_salary_iqd', 'labor_kind'])
            ->mapWithKeys(function (Worker $worker) use ($from, $to) {
                $calc = $this->payroll->calculate($worker, $from, $to);

                return [
                    $worker->id => [
                        'net_pay_usd' => $calc['net_pay_usd'],
                        'gross_pay_usd' => $calc['gross_pay_usd'],
                        'penalties_usd' => $calc['penalties_usd'],
                        'advances_usd' => $calc['advances_usd'],
                        'advances_iqd' => $calc['advances_iqd'],
                        'insurance_holdback_usd' => $calc['insurance_holdback_usd'],
                        'insurance_holdback_pct' => $calc['insurance_holdback_pct'],
                        'month' => $from->format('Y-m'),
                    ],
                ];
            });

        $availableCash = null;
        try {
            $availableCash = $this->liquidity->dualSnapshot();
        } catch (\InvalidArgumentException) {
            $availableCash = [
                'available_usd' => 0,
                'available_iqd' => 0,
                'balance_usd' => 0,
                'balance_iqd' => 0,
            ];
        }

        return Inertia::render('Payouts/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id', 'labor_kind']),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name', 'tower_id']),
            'vaults' => Vault::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Payout::CATEGORIES,
            'currencies' => DualCurrency::CURRENCIES,
            'payrollSuggestions' => $payrollSuggestions,
            'availableCash' => $availableCash,
        ]);
    }

    public function store(StorePayoutRequest $request): RedirectResponse
    {
        $this->authorize('create', Payout::class);

        try {
            $payout = $this->payouts->create([
                ...$request->validated(),
                'created_by' => $request->user()?->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('payouts.show', $payout)
            ->with('success', 'Staff payment / salary run created (pending ability to pay).');
    }

    public function show(Payout $payout): Response
    {
        $this->authorize('view', $payout);

        $payout->load(['project', 'worker', 'floor', 'vault', 'retentionHolds', 'penalties']);

        $currency = strtoupper((string) ($payout->currency ?: DualCurrency::USD));
        $amount = $currency === DualCurrency::USD
            ? (float) $payout->amount_usd
            : (float) $payout->amount_iqd;

        $ability = null;
        if ($payout->isAwaitingPayAbility()) {
            $ability = $this->liquidity->canPayCurrency(
                $payout->project,
                $payout->category,
                $currency,
                max($amount, 0.01),
                $payout->vault,
                excludePayoutId: $payout->id,
            );
        }

        return Inertia::render('Payouts/Show', [
            'payout' => $payout,
            'availableCash' => $this->liquidity->dualSnapshot($payout->vault),
            'payAbility' => $ability,
        ]);
    }

    public function approve(Payout $payout): RedirectResponse
    {
        $this->authorize('approve', $payout);

        try {
            $this->payouts->approve($payout, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Approved — ability to pay confirmed; Available Cash updated.');
    }

    public function hold(Request $request, Payout $payout): RedirectResponse
    {
        $this->authorize('hold', $payout);

        $notes = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ])['notes'] ?? null;

        try {
            $this->payouts->hold($payout, $notes, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Held — ability to pay deferred.');
    }

    public function reject(RejectPayoutRequest $request, Payout $payout): RedirectResponse
    {
        $this->authorize('reject', $payout);

        try {
            $this->payouts->reject($payout, $request->validated('notes'), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Payout rejected.');
    }

    public function reconcile(Payout $payout): RedirectResponse
    {
        $this->authorize('reconcile', $payout);

        try {
            $this->payouts->reconcile($payout);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Payout reconciled.');
    }
}
