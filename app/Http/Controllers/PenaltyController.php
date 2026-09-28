<?php

namespace App\Http\Controllers;

use App\Http\Requests\Penalty\LinkPenaltyRequest;
use App\Http\Requests\Penalty\StorePenaltyRequest;
use App\Models\Floor;
use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Worker;
use App\Services\ExchangeRateService;
use App\Services\PenaltyService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class PenaltyController extends Controller
{
    public function __construct(
        private readonly PenaltyService $penalties,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Penalty::class);

        $rate = $this->exchangeRates->getUsdToIqd();

        $penalties = Penalty::query()
            ->with(['worker:id,name', 'project:id,name', 'payout:id,status,amount_usd', 'creator:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(function (Penalty $p) use ($rate) {
                $iqd = $p->amount_iqd !== null
                    ? (float) $p->amount_iqd
                    : round((float) $p->amount_usd * $rate, 0);
                $p->setAttribute('amount_iqd_display', $iqd);

                return $p;
            });

        return Inertia::render('Penalties/Index', [
            'penalties' => $penalties,
            'exchangeRate' => $rate,
            'types' => Penalty::TYPES,
            'statuses' => Penalty::STATUSES,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Penalty::class);

        return Inertia::render('Penalties/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name']),
            'payouts' => Payout::query()
                ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_APPROVED])
                ->with('worker:id,name')
                ->orderByDesc('id')
                ->get(['id', 'worker_id', 'project_id', 'amount_usd', 'status', 'category']),
            'types' => Penalty::TYPES,
            'defaults' => [
                'occurred_on' => now()->toDateString(),
                'type' => Penalty::TYPE_OTHER,
            ],
        ]);
    }

    public function store(StorePenaltyRequest $request): RedirectResponse
    {
        $this->authorize('create', Penalty::class);

        try {
            $penalty = $this->penalties->create([
                ...$request->validated(),
                'created_by' => $request->user()?->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount_iqd' => $e->getMessage()]);
        }

        return redirect()
            ->route('penalties.show', $penalty)
            ->with('success', __('Penalty recorded.'));
    }

    public function show(Penalty $penalty): Response
    {
        $this->authorize('view', $penalty);

        $penalty->load(['worker', 'project', 'floor', 'payout', 'creator:id,name']);
        $rate = $this->exchangeRates->getUsdToIqd();
        $penalty->setAttribute(
            'amount_iqd_display',
            $penalty->amount_iqd !== null
                ? (float) $penalty->amount_iqd
                : round((float) $penalty->amount_usd * $rate, 0),
        );

        return Inertia::render('Penalties/Show', [
            'penalty' => $penalty,
            'linkablePayouts' => Payout::query()
                ->where('worker_id', $penalty->worker_id)
                ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_APPROVED])
                ->orderByDesc('id')
                ->get(['id', 'amount_usd', 'status', 'category']),
            'exchangeRate' => $rate,
        ]);
    }

    public function apply(Penalty $penalty): RedirectResponse
    {
        $this->authorize('apply', $penalty);

        try {
            $this->penalties->apply($penalty);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Penalty applied to payroll.'));
    }

    public function waive(Penalty $penalty): RedirectResponse
    {
        $this->authorize('waive', $penalty);

        try {
            $this->penalties->waive($penalty);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Penalty waived.'));
    }

    public function link(LinkPenaltyRequest $request, Penalty $penalty): RedirectResponse
    {
        $this->authorize('link', $penalty);

        try {
            $payout = Payout::query()->findOrFail($request->validated('payout_id'));
            $this->penalties->linkToPayout($penalty, $payout);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payout_id' => $e->getMessage()]);
        }

        return back()->with('success', __('Penalty linked; will deduct on payout reconcile.'));
    }
}
