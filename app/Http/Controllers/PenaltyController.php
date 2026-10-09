<?php

namespace App\Http\Controllers;

use App\Http\Requests\Penalty\StorePenaltyRequest;
use App\Models\Floor;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Staff;
use App\Services\ExchangeRateService;
use App\Services\PenaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class PenaltyController extends Controller
{
    public function __construct(
        private readonly PenaltyService $penalties,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Penalty::class);

        $rate = $this->exchangeRates->getUsdToIqd();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        if ($status !== '' && ! in_array($status, Penalty::STATUSES, true)) {
            $status = '';
        }
        if ($type !== '' && ! in_array($type, Penalty::TYPES, true)) {
            $type = '';
        }

        $query = Penalty::query()
            ->with(['staff:id,name', 'project:id,name', 'payout:id,status,amount_usd', 'creator:id,name'])
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($type !== '') {
            $query->where('type', $type);
        }

        $penalties = $query->get()->map(function (Penalty $p) use ($rate) {
            $p->setAttribute('amount_iqd_display', $this->displayIqd($p, $rate));
            $p->setAttribute('amount_usd_display', (float) $p->amount_usd);

            return $p;
        });

        $scoped = Penalty::query();
        if ($type !== '') {
            $scoped->where('type', $type);
        }
        $statusRows = (clone $scoped)->get(['status']);
        $statusCounts = [
            'all' => $statusRows->count(),
            'pending' => $statusRows->where('status', Penalty::STATUS_PENDING)->count(),
            'applied' => $statusRows->where('status', Penalty::STATUS_APPLIED)->count(),
            'waived' => $statusRows->where('status', Penalty::STATUS_WAIVED)->count(),
        ];

        $typeScoped = Penalty::query();
        if ($status !== '') {
            $typeScoped->where('status', $status);
        }
        $typeRows = (clone $typeScoped)->get(['type']);
        $typeCounts = collect(Penalty::TYPES)
            ->mapWithKeys(fn (string $t) => [$t => $typeRows->where('type', $t)->count()])
            ->all();

        $overview = $this->buildOverview($penalties, $rate);

        return Inertia::render('Penalties/Index', [
            'penalties' => $penalties,
            'exchangeRate' => $rate,
            'types' => Penalty::TYPES,
            'statuses' => Penalty::STATUSES,
            'filters' => [
                'status' => $status !== '' ? $status : null,
                'type' => $type !== '' ? $type : null,
            ],
            'statusCounts' => $statusCounts,
            'typeCounts' => $typeCounts,
            'overview' => $overview,
        ]);
    }

    /**
     * @param  Collection<int, Penalty>  $penalties
     * @return array{
     *     count: int,
     *     pending: int,
     *     applied: int,
     *     waived: int,
     *     amount_usd: float,
     *     amount_iqd: float,
     *     pending_usd: float,
     *     pending_iqd: float
     * }
     */
    private function buildOverview(Collection $penalties, float $rate): array
    {
        $active = $penalties->whereIn('status', [
            Penalty::STATUS_PENDING,
            Penalty::STATUS_APPLIED,
        ]);
        $pending = $penalties->where('status', Penalty::STATUS_PENDING);

        return [
            'count' => $penalties->count(),
            'pending' => $pending->count(),
            'applied' => $penalties->where('status', Penalty::STATUS_APPLIED)->count(),
            'waived' => $penalties->where('status', Penalty::STATUS_WAIVED)->count(),
            'amount_usd' => round((float) $active->sum(fn (Penalty $p) => (float) $p->amount_usd), 2),
            'amount_iqd' => round((float) $active->sum(fn (Penalty $p) => $this->displayIqd($p, $rate)), 0),
            'pending_usd' => round((float) $pending->sum(fn (Penalty $p) => (float) $p->amount_usd), 2),
            'pending_iqd' => round((float) $pending->sum(fn (Penalty $p) => $this->displayIqd($p, $rate)), 0),
        ];
    }

    private function displayIqd(Penalty $penalty, float $rate): float
    {
        if ($penalty->amount_iqd !== null) {
            return round((float) $penalty->amount_iqd, 0);
        }

        return round((float) $penalty->amount_usd * $rate, 0);
    }

    public function create(): Response
    {
        $this->authorize('create', Penalty::class);

        return Inertia::render('Penalties/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'staff' => Staff::query()->orderBy('name')->get(['id', 'name', 'kind', 'trade']),
            'floors' => Floor::query()->orderBy('name')->get(['id', 'name']),
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

        $penalty->load(['staff', 'project', 'floor', 'creator:id,name']);
        $rate = $this->exchangeRates->getUsdToIqd();
        $penalty->setAttribute(
            'amount_iqd_display',
            $penalty->amount_iqd !== null
                ? (float) $penalty->amount_iqd
                : round((float) $penalty->amount_usd * $rate, 0),
        );

        return Inertia::render('Penalties/Show', [
            'penalty' => $penalty,
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

        return back()->with('success', __('Penalty applied.'));
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
}
