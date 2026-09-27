<?php

namespace App\Http\Controllers;

use App\Models\RetentionHold;
use App\Services\RetentionHoldService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class RetentionHoldController extends Controller
{
    public function __construct(
        private readonly RetentionHoldService $holds,
    ) {}

    public function index(): Response
    {
        return Inertia::render('RetentionHolds/Index', [
            'holds' => RetentionHold::query()
                ->with(['worker:id,name', 'project:id,name', 'payout:id,status'])
                ->orderByDesc('id')
                ->get(),
            'matured' => $this->holds->maturedAwaitingRelease(),
        ]);
    }

    public function release(RetentionHold $retentionHold): RedirectResponse
    {
        try {
            $this->holds->release($retentionHold, request()->user()?->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Insurance released to staff payroll pool.');
    }
}
