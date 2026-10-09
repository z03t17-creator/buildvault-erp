<?php

namespace App\Http\Controllers;

use App\Models\Vault;
use App\Services\MonthlySettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class MonthlySettlementController extends Controller
{
    public function __construct(
        private readonly MonthlySettlementService $settlements,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewSettlement', Vault::class);

        $yearMonth = (string) $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $yearMonth = now()->format('Y-m');
        }

        $projectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $requested = (float) $request->input('requested_payout_iqd', 0);

        try {
            $payload = $this->settlements->preview($yearMonth, $projectId, null, $requested);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['month' => $e->getMessage()]);
        }

        return Inertia::render('Settlements/Index', [
            'settlement' => $payload,
            'canSave' => $request->user()?->can('manageSettlement', Vault::class) ?? false,
            'filters' => [
                'month' => $yearMonth,
                'project_id' => $projectId,
                'requested_payout_iqd' => $requested > 0 ? $requested : '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageSettlement', Vault::class);

        $data = $request->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'requested_payout_iqd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : null;
        $requested = (float) ($data['requested_payout_iqd'] ?? 0);

        try {
            $this->settlements->saveSnapshot(
                $data['month'],
                $projectId,
                $request->user(),
                null,
                ['requested_payout_iqd' => $requested],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['requested_payout_iqd' => $e->getMessage()]);
        }

        return redirect()
            ->route('settlements.index', [
                'month' => $data['month'],
                'project_id' => $projectId,
            ])
            ->with('success', __('Settlement snapshot saved.'));
    }
}
