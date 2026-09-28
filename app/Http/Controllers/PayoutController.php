<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payout\RejectPayoutRequest;
use App\Http\Requests\Payout\StorePayoutRequest;
use App\Models\Floor;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\PayrollService;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly PayrollService $payroll,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Payout::class);

        $query = Payout::query()
            ->with(['project:id,name', 'worker:id,name', 'vault:id,name'])
            ->orderByDesc('id');

        return Inertia::render('Payouts/Index', [
            'payouts' => $query->get(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Payout::class);

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $rate = $this->exchangeRates->getUsdToIqd();

        $payrollSuggestions = Worker::query()
            ->orderBy('name')
            ->get(['id', 'name', 'project_id', 'daily_rate_usd', 'overtime_rate_usd'])
            ->mapWithKeys(function (Worker $worker) use ($from, $to, $rate) {
                $calc = $this->payroll->calculate($worker, $from, $to);

                return [
                    $worker->id => [
                        'net_pay_usd' => $calc['net_pay_usd'],
                        'net_pay_iqd' => round((float) $calc['net_pay_usd'] * $rate, 0),
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

        return Inertia::render('Payouts/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name', 'tower_id']),
            'vaults' => Vault::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Payout::CATEGORIES,
            'payrollSuggestions' => $payrollSuggestions,
            'exchangeRate' => $rate,
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
            return back()->withErrors(['amount_usd' => $e->getMessage()]);
        }

        return redirect()
            ->route('payouts.show', $payout)
            ->with('success', 'Payout created (pending).');
    }

    public function show(Payout $payout): Response
    {
        $this->authorize('view', $payout);

        $payout->load(['project', 'worker', 'floor', 'vault', 'retentionHolds', 'penalties']);

        return Inertia::render('Payouts/Show', [
            'payout' => $payout,
        ]);
    }

    public function approve(Payout $payout): RedirectResponse
    {
        $this->authorize('approve', $payout);

        try {
            $this->payouts->approve($payout);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Payout approved; vault/pool debited.');
    }

    public function reject(RejectPayoutRequest $request, Payout $payout): RedirectResponse
    {
        $this->authorize('reject', $payout);

        try {
            $this->payouts->reject($payout, $request->validated('notes'));
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
