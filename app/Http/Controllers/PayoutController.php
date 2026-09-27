<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payout\RejectPayoutRequest;
use App\Http\Requests\Payout\StorePayoutRequest;
use App\Models\Floor;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payouts,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Payout::class);

        $query = Payout::query()
            ->with(['project:id,name', 'worker:id,name', 'vault:id,name'])
            ->orderByDesc('id');

        $user = request()->user();
        if ($user?->hasRole(\App\Support\Roles::WORKER) && $user->worker) {
            $query->where('worker_id', $user->worker->id);
        }

        return Inertia::render('Payouts/Index', [
            'payouts' => $query->get(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Payout::class);

        return Inertia::render('Payouts/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name', 'tower_id']),
            'vaults' => Vault::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Payout::CATEGORIES,
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
