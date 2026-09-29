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

    public function index(): Response
    {
        $this->authorize('viewAny', Payout::class);

        $query = Payout::query()
            ->with(['project:id,name', 'worker:id,name', 'vault:id,name'])
            ->orderByDesc('id');

        return Inertia::render('Payouts/Index', [
            'payouts' => $query->get(),
            'statuses' => Payout::STATUSES,
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
