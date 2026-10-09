<?php

namespace App\Http\Controllers;

use App\Http\Requests\Production\StoreProductionRequest;
use App\Http\Requests\Production\UpdateProductionRequest;
use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\Worker;
use App\Services\ProductionRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class ProductionRecordController extends Controller
{
    public function __construct(
        private readonly ProductionRecordService $productions,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProductionRecord::class);

        $projectId = $request->integer('project_id') ?: null;
        $workerId = $request->integer('worker_id') ?: null;

        $query = ProductionRecord::query()
            ->with(['worker:id,name', 'project:id,name', 'enteredBy:id,name'])
            ->orderByDesc('recorded_on')
            ->orderByDesc('id');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($workerId) {
            $query->where('worker_id', $workerId);
        }

        return Inertia::render('Productions/Index', [
            'productions' => $query->get(),
            'filters' => [
                'project_id' => $projectId,
                'worker_id' => $workerId,
            ],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'unitTypes' => ProductionRecord::UNIT_TYPES,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ProductionRecord::class);

        return Inertia::render('Productions/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'unitTypes' => ProductionRecord::UNIT_TYPES,
            'defaults' => [
                'recorded_on' => now()->toDateString(),
                'unit_type' => ProductionRecord::UNIT_APARTMENT,
                'assigned' => 0,
                'completed' => 0,
                'received' => 0,
            ],
        ]);
    }

    public function store(StoreProductionRequest $request): RedirectResponse
    {
        $this->authorize('create', ProductionRecord::class);

        try {
            $record = $this->productions->create(
                $request->validated(),
                Auth::user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['assigned' => $e->getMessage()]);
        }

        return redirect()
            ->route('productions.show', $record)
            ->with('success', __('Production recorded.'));
    }

    public function show(ProductionRecord $production): Response
    {
        $this->authorize('view', $production);

        $production->load(['worker', 'project', 'enteredBy:id,name']);

        return Inertia::render('Productions/Show', [
            'production' => $production,
            'unitTypes' => ProductionRecord::UNIT_TYPES,
        ]);
    }

    public function edit(ProductionRecord $production): Response
    {
        $this->authorize('update', $production);

        return Inertia::render('Productions/Edit', [
            'production' => $production->load(['worker', 'project']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'workers' => Worker::query()->orderBy('name')->get(['id', 'name', 'project_id']),
            'unitTypes' => ProductionRecord::UNIT_TYPES,
        ]);
    }

    public function update(UpdateProductionRequest $request, ProductionRecord $production): RedirectResponse
    {
        $this->authorize('update', $production);

        try {
            $this->productions->update($production, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['assigned' => $e->getMessage()]);
        }

        return redirect()
            ->route('productions.show', $production)
            ->with('success', __('Production updated.'));
    }
}
