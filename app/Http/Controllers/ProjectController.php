<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectReceiptRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ExchangeRateService;
use App\Services\ProjectFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly ProjectFinancialService $financials,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Project::class);

        $canFinancials = Gate::allows('viewFinancials', new Project);

        $projects = Project::query()
            ->withCount(['towers', 'workers'])
            ->orderByDesc('id')
            ->get()
            ->map(function (Project $project) use ($canFinancials) {
                $row = $project->toArray();
                $row['towers_count'] = $project->towers_count;
                $row['workers_count'] = $project->workers_count;
                if ($canFinancials) {
                    $summary = $this->financials->summary($project);
                    $row['financial_summary'] = [
                        'contract_value_iqd' => $summary['contract_value_iqd'],
                        'money_received_iqd' => $summary['money_received_iqd'],
                        'remaining_vs_contract_iqd' => $summary['remaining_vs_contract_iqd'],
                        'net_position_iqd' => $summary['net_position_iqd'],
                    ];
                }

                return $row;
            });

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'canViewFinancials' => $canFinancials,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Projects/Create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $request->validated();
        // Form no longer collects status; model default is planning.
        $data['status'] = $data['status'] ?? Project::STATUS_PLANNING;

        $project = Project::query()->create($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created.');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'towers.floors',
            'workers',
            'receipts' => fn ($q) => $q->with('enteredBy:id,name')->orderByDesc('received_on')->orderByDesc('id'),
        ]);

        $canFinancials = Gate::allows('viewFinancials', $project);
        $canRecordReceipt = Gate::allows('recordReceipt', $project);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'exchangeRate' => $this->exchangeRates->getUsdToIqd(),
            'financialSummary' => $canFinancials ? $this->financials->summary($project) : null,
            'recentMaterials' => $canFinancials
                ? $this->financials->recentMaterialsUsed($project)
                : [],
            'canViewFinancials' => $canFinancials,
            'canRecordReceipt' => $canRecordReceipt,
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Edit', [
            'project' => $project,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted.');
    }

    public function storeReceipt(StoreProjectReceiptRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('recordReceipt', $project);

        $this->financials->recordReceipt($project, [
            ...$request->validated(),
            'entered_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Money received recorded.'));
    }
}
