<?php

namespace App\Http\Controllers;

use App\Http\Requests\Worker\StoreWorkerRequest;
use App\Http\Requests\Worker\UpdateWorkerRequest;
use App\Models\EmployeeAdvance;
use App\Models\Project;
use App\Models\StaffStatement;
use App\Models\Worker;
use App\Services\AuditLogger;
use App\Services\StaffSettlementService;
use App\Support\AuditActions;
use App\Support\DualCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public const AVATAR_DIR = 'uploads/workers';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Worker::class);

        $kind = $request->string('labor_kind')->toString();

        $query = Worker::query()
            ->with('project:id,name')
            ->orderBy('name');

        if (in_array($kind, Worker::LABOR_KINDS, true)) {
            $query->where('labor_kind', $kind);
        }

        $kindCounts = Worker::query()
            ->selectRaw('labor_kind, count(*) as aggregate')
            ->groupBy('labor_kind')
            ->pluck('aggregate', 'labor_kind');

        $workers = $query->get();

        $salaryTotals = [
            'monthly_salary_usd' => (float) Worker::query()
                ->where('labor_kind', Worker::LABOR_KIND_WORKER)
                ->sum('monthly_salary_usd'),
            'monthly_salary_iqd' => (float) Worker::query()
                ->where('labor_kind', Worker::LABOR_KIND_WORKER)
                ->sum('monthly_salary_iqd'),
        ];

        return Inertia::render('Workers/Index', [
            'workers' => $workers,
            'filters' => ['labor_kind' => $kind],
            'laborKinds' => Worker::LABOR_KINDS,
            'kindCounts' => [
                'all' => (int) $kindCounts->sum(),
                'unclassified' => (int) ($kindCounts[Worker::LABOR_KIND_UNCLASSIFIED] ?? 0),
                'staff' => (int) ($kindCounts[Worker::LABOR_KIND_STAFF] ?? 0),
                'worker' => (int) ($kindCounts[Worker::LABOR_KIND_WORKER] ?? 0),
            ],
            'salaryTotals' => $salaryTotals,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Worker::class);

        return Inertia::render('Workers/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Worker::ROLES,
            'laborKinds' => Worker::LABOR_KINDS,
            'currencies' => DualCurrency::CURRENCIES,
        ]);
    }

    public function store(StoreWorkerRequest $request): RedirectResponse
    {
        $this->authorize('create', Worker::class);

        $data = $request->safe()->except(['avatar']);
        $data['labor_kind'] = $data['labor_kind'] ?? Worker::LABOR_KIND_UNCLASSIFIED;

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $this->storeAvatar($request->file('avatar'));
        }

        $worker = Worker::query()->create($data);

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', 'Person created. Set Staff or Worker when ready.');
    }

    public function show(Worker $worker): Response
    {
        $this->authorize('view', $worker);

        $worker->load('project');

        $advances = EmployeeAdvance::query()
            ->where('worker_id', $worker->id)
            ->orderByDesc('advanced_on')
            ->orderByDesc('id')
            ->get();

        $statements = StaffStatement::query()
            ->where('worker_id', $worker->id)
            ->with('project:id,name')
            ->orderByDesc('id')
            ->get();

        $settlement = null;
        if ($worker->isStaff() || $worker->isEmployee()) {
            try {
                $settlement = app(StaffSettlementService::class)->preview($worker);
            } catch (\InvalidArgumentException) {
                $settlement = null;
            }
        }

        return Inertia::render('Workers/Show', [
            'worker' => $worker,
            'advances' => $advances,
            'statements' => $statements,
            'settlement' => $settlement,
            'laborKinds' => [Worker::LABOR_KIND_STAFF, Worker::LABOR_KIND_WORKER],
            'canClassify' => request()->user()?->can('classify', $worker) ?? false,
        ]);
    }

    public function edit(Worker $worker): Response
    {
        $this->authorize('update', $worker);

        return Inertia::render('Workers/Edit', [
            'worker' => $worker,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Worker::ROLES,
            'laborKinds' => Worker::LABOR_KINDS,
            'currencies' => DualCurrency::CURRENCIES,
        ]);
    }

    public function update(UpdateWorkerRequest $request, Worker $worker): RedirectResponse
    {
        $this->authorize('update', $worker);

        $data = $request->safe()->except(['avatar']);

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($worker->avatar_path);
            $data['avatar_path'] = $this->storeAvatar($request->file('avatar'));
        }

        $worker->update($data);

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', 'Person updated.');
    }

    public function classify(Request $request, Worker $worker): RedirectResponse
    {
        $this->authorize('classify', $worker);

        $data = $request->validate([
            'labor_kind' => ['required', Rule::in([Worker::LABOR_KIND_STAFF, Worker::LABOR_KIND_WORKER])],
            'rate_unit' => ['nullable', 'string', 'max:50'],
            'rate_currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
            'unit_rate' => ['nullable', 'numeric', 'min:0'],
            'monthly_salary_usd' => ['nullable', 'numeric', 'min:0'],
            'monthly_salary_iqd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $worker->classify($data['labor_kind'], $request->user()?->id);

        if ($data['labor_kind'] === Worker::LABOR_KIND_STAFF) {
            $worker->fill([
                'rate_unit' => $data['rate_unit'] ?? $worker->rate_unit,
                'rate_currency' => $data['rate_currency'] ?? $worker->rate_currency,
                'unit_rate' => $data['unit_rate'] ?? $worker->unit_rate,
            ]);
        }

        if ($data['labor_kind'] === Worker::LABOR_KIND_WORKER) {
            $worker->fill([
                'monthly_salary_usd' => $data['monthly_salary_usd'] ?? $worker->monthly_salary_usd,
                'monthly_salary_iqd' => $data['monthly_salary_iqd'] ?? $worker->monthly_salary_iqd,
            ]);
        }

        $worker->save();

        $this->audit->log(
            AuditActions::PERSON_CLASSIFIED,
            sprintf('Person #%d classified as %s', $worker->id, $data['labor_kind']),
            $worker,
            ['worker_id' => $worker->id, 'labor_kind' => $data['labor_kind']],
            $request->user(),
        );

        $label = $data['labor_kind'] === Worker::LABOR_KIND_STAFF ? 'Staff' : 'Worker';

        return back()->with('success', "Classified as {$label}.");
    }

    public function storeStatement(Request $request, Worker $worker): RedirectResponse
    {
        $this->authorize('update', $worker);
        abort_unless($worker->isStaff(), 422, 'Staff statements require a Staff person.');

        $data = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'period' => ['nullable', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:255'],
            'earned_usd' => ['nullable', 'numeric', 'min:0'],
            'earned_iqd' => ['nullable', 'numeric', 'min:0'],
            'paid_usd' => ['nullable', 'numeric', 'min:0'],
            'paid_iqd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $statement = new StaffStatement([
            ...$data,
            'worker_id' => $worker->id,
            'created_by' => $request->user()?->id,
        ]);
        $statement->save();

        // Gross − 10% retention − all advances − penalties (per currency)
        app(StaffSettlementService::class)->refreshStatement($statement);

        $this->audit->log(
            AuditActions::STAFF_STATEMENT_SAVED,
            sprintf('Staff statement #%d for person #%d', $statement->id, $worker->id),
            $statement,
            ['staff_statement_id' => $statement->id, 'worker_id' => $worker->id],
            $request->user(),
        );

        return back()->with('success', 'Staff statement saved.');
    }

    public function destroy(Worker $worker): RedirectResponse
    {
        $this->authorize('delete', $worker);

        $this->deleteAvatar($worker->avatar_path);
        $worker->delete();

        return redirect()
            ->route('workers.index')
            ->with('success', 'Person soft-deleted.');
    }

    protected function storeAvatar(UploadedFile $file): string
    {
        return $file->store(self::AVATAR_DIR, 'public');
    }

    protected function deleteAvatar(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
