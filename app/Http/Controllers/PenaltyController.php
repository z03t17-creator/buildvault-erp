<?php

namespace App\Http\Controllers;

use App\Http\Requests\Penalty\LinkPenaltyRequest;
use App\Http\Requests\Penalty\StorePenaltyRequest;
use App\Models\Floor;
use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Worker;
use App\Services\PenaltyService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class PenaltyController extends Controller
{
    public function __construct(
        private readonly PenaltyService $penalties,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Penalties/Index', [
            'penalties' => Penalty::query()
                ->with(['worker:id,name', 'project:id,name', 'payout:id,status,amount_usd'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Penalties/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name']),
            'payouts' => Payout::query()
                ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_APPROVED])
                ->with('worker:id,name')
                ->orderByDesc('id')
                ->get(['id', 'worker_id', 'project_id', 'amount_usd', 'status', 'category']),
        ]);
    }

    public function store(StorePenaltyRequest $request): RedirectResponse
    {
        try {
            $penalty = $this->penalties->create($request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount_usd' => $e->getMessage()]);
        }

        return redirect()
            ->route('penalties.show', $penalty)
            ->with('success', 'Penalty recorded.');
    }

    public function show(Penalty $penalty): Response
    {
        $penalty->load(['worker', 'project', 'floor', 'payout']);

        return Inertia::render('Penalties/Show', [
            'penalty' => $penalty,
            'linkablePayouts' => Payout::query()
                ->where('worker_id', $penalty->worker_id)
                ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_APPROVED])
                ->orderByDesc('id')
                ->get(['id', 'amount_usd', 'status', 'category']),
        ]);
    }

    public function waive(Penalty $penalty): RedirectResponse
    {
        try {
            $this->penalties->waive($penalty);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Penalty waived.');
    }

    public function link(LinkPenaltyRequest $request, Penalty $penalty): RedirectResponse
    {
        try {
            $payout = Payout::query()->findOrFail($request->validated('payout_id'));
            $this->penalties->linkToPayout($penalty, $payout);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payout_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Penalty linked; will deduct on payout reconcile.');
    }
}
