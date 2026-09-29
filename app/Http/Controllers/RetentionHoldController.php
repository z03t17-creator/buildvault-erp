<?php

namespace App\Http\Controllers;

use App\Models\RetentionHold;
use App\Models\Vault;
use App\Services\ExchangeRateService;
use App\Services\InsuranceSettings;
use App\Services\RetentionHoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class RetentionHoldController extends Controller
{
    public function __construct(
        private readonly RetentionHoldService $holds,
        private readonly InsuranceSettings $insurance,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewRetention', Vault::class);

        $rate = $this->exchangeRates->getUsdToIqd();
        // Use native dual columns — do not invent IQD via FX.
        $mapHold = static function (RetentionHold $h): RetentionHold {
            $h->setAttribute('amount_iqd', round((float) ($h->amount_iqd ?? 0), 2));
            if ($h->released_amount_usd !== null || $h->released_amount_iqd !== null) {
                $h->setAttribute(
                    'released_amount_iqd',
                    round((float) ($h->released_amount_iqd ?? 0), 2),
                );
            }

            return $h;
        };

        return Inertia::render('RetentionHolds/Index', [
            'holds' => RetentionHold::query()
                ->with(['worker:id,name', 'project:id,name', 'payout:id,status'])
                ->orderByDesc('id')
                ->get()
                ->map($mapHold),
            'matured' => collect($this->holds->maturedAwaitingRelease())->map($mapHold)->values(),
            'settings' => $this->insurance->all(),
            'exchangeRate' => $rate,
            'autoBlendDisabled' => true,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('manageRetention', Vault::class);

        $validated = $request->validate([
            'holdback_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'maturity_months' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        try {
            $this->insurance->update(
                (float) $validated['holdback_pct'],
                (int) $validated['maturity_months'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['holdback_pct' => $e->getMessage()]);
        }

        return back()->with('success', __('Insurance settings saved.'));
    }

    public function release(RetentionHold $retentionHold): RedirectResponse
    {
        $this->authorize('manageRetention', Vault::class);

        try {
            $this->holds->release($retentionHold, request()->user()?->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', 'Insurance released to staff payroll pool.');
    }
}
