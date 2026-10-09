<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Vault;
use App\Services\ExchangeRateService;
use App\Services\SimpleVaultService;
use App\Support\NumberFormat;
use Database\Seeders\VaultSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quiet vault home — Available Cash, insurance unlocks, staff holds, salary/expense estimate.
 * Backed only by SimpleVaultService / vault_lines (no second ledger).
 */
class VaultDashboardController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly SimpleVaultService $simpleVault,
    ) {}

    public function show(): Response
    {
        $this->authorize('viewDashboard', Vault::class);

        $vault = Vault::query()->where('name', VaultSeeder::NAME)->first()
            ?? Vault::query()->orderBy('id')->first()
            ?? $this->simpleVault->zhakoVault();

        $snapshot = $this->simpleVault->dashboardSnapshot($vault);
        $rate = $this->exchangeRates->getUsdToIqd();
        $latestFx = ExchangeRate::query()
            ->where('base_currency', 'USD')
            ->where('target_currency', 'IQD')
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('Dashboards/Vault', [
            'vault' => [
                'id' => $vault->id,
                'name' => $vault->name,
                'balance_usd' => (float) $vault->balance_usd,
                'balance_iqd' => (float) $vault->balance_iqd,
            ],
            'as_of' => $snapshot['as_of'],
            'available_cash' => $snapshot['available_cash'],
            'insurance_unlocks' => $snapshot['insurance_unlocks'],
            'staff_holds' => $snapshot['staff_holds'],
            'estimates' => $snapshot['estimates'],
            // Compat for RoleQa / older assertions — native Available Cash only.
            'liquidity' => [
                'available_usd' => $snapshot['available_cash']['USD'],
                'available_iqd' => $snapshot['available_cash']['IQD'],
                'pending_payouts_usd' => 0.0,
                'pending_payouts_iqd' => 0.0,
                'reserved_insurance_usd' => $snapshot['estimates']['USD']['company_insurance_held'],
                'reserved_insurance_iqd' => $snapshot['estimates']['IQD']['company_insurance_held'],
            ],
            'fx' => [
                'rate' => $rate,
                'source' => $latestFx?->source ?? ExchangeRateService::SOURCE_FALLBACK,
                'fetched_at' => $latestFx?->fetched_at?->toIso8601String(),
                'fallback_rate' => ExchangeRateService::FALLBACK_RATE,
                'auto_blend' => false,
            ],
        ]);
    }

    public function refreshFx(): RedirectResponse
    {
        $this->authorize('refreshFx', Vault::class);

        $rate = $this->exchangeRates->refresh();

        return back()->with('success', sprintf('FX refreshed: 1 USD = %s IQD', NumberFormat::number($rate, 2)));
    }

    public function overrideFx(Request $request): RedirectResponse
    {
        $this->authorize('overrideFx', Vault::class);

        $data = $request->validate([
            'rate' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $rate = $this->exchangeRates->override((float) $data['rate'], $data['note'] ?? null);

        return back()->with('success', sprintf('FX overridden: 1 USD = %s IQD', NumberFormat::number($rate, 2)));
    }
}
