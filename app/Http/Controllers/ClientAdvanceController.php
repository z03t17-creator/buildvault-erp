<?php

namespace App\Http\Controllers;

use App\Models\ClientAdvance;
use App\Models\Project;
use App\Models\Vault;
use App\Services\ClientAdvanceService;
use App\Support\DualCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ClientAdvanceController extends Controller
{
    public function __construct(
        private readonly ClientAdvanceService $advances,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ClientAdvance::class);

        return Inertia::render('ClientAdvances/Index', [
            'advances' => ClientAdvance::query()
                ->with(['project:id,name', 'retentionHolds'])
                ->orderByDesc('received_on')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ClientAdvance::class);

        return Inertia::render('ClientAdvances/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => DualCurrency::CURRENCIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ClientAdvance::class);

        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'received_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lock_retention' => ['sometimes', 'boolean'],
        ]);

        try {
            $advance = $this->advances->create([
                ...$data,
                'lock_retention' => $request->boolean('lock_retention', true),
            ], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('client-advances.show', $advance)
            ->with('success', 'Client advance recorded (Money In).');
    }

    public function show(ClientAdvance $clientAdvance): Response
    {
        $this->authorize('view', $clientAdvance);

        return Inertia::render('ClientAdvances/Show', [
            'advance' => $clientAdvance->load(['project', 'retentionHolds', 'transaction', 'enteredBy']),
        ]);
    }

    public function destroy(ClientAdvance $clientAdvance): RedirectResponse
    {
        $this->authorize('delete', $clientAdvance);

        $this->advances->softDelete($clientAdvance, request()->user());

        return redirect()
            ->route('client-advances.index')
            ->with('success', 'Client advance soft-deleted.');
    }
}
