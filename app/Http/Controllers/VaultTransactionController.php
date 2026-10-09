<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Transaction;
use App\Models\Vault;
use App\Services\LiquidityService;
use App\Services\VaultLedgerService;
use App\Services\VaultLedgerWriteService;
use App\Support\DualCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class VaultTransactionController extends Controller
{
    public function __construct(
        private readonly VaultLedgerService $ledger,
        private readonly VaultLedgerWriteService $writes,
        private readonly LiquidityService $liquidity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewLedger', Vault::class);

        $payload = $this->ledger->listing(null, $request->only([
            'type',
            'project_id',
            'user_id',
            'from',
            'to',
            'q',
            'include_non_cash',
            'per_page',
        ]));

        $vault = $payload['vault'];
        $balances = $payload['balances'];
        $snap = $this->liquidity->dualSnapshot($vault);

        return Inertia::render('Vault/Transactions', [
            'vault' => [
                'id' => $vault->id,
                'name' => $vault->name,
            ],
            'balances' => [
                'current_usd' => $balances['current_usd'],
                'current_iqd' => $balances['current_iqd'],
                'available_usd' => $snap['available_usd'],
                'available_iqd' => $snap['available_iqd'],
                'reserved_usd' => $snap['reserved_usd'],
                'reserved_iqd' => $snap['reserved_iqd'],
                'pending_usd' => $snap['pending_usd'],
                'pending_iqd' => $snap['pending_iqd'],
                'ledger_cash_iqd' => $balances['ledger_cash_iqd'],
                'ledger_cash_usd' => $balances['ledger_cash_usd'] ?? null,
                'balance_matches_ledger' => $balances['balance_matches_ledger'],
            ],
            'transactions' => $payload['transactions'],
            'filters' => $payload['filters'],
            'types' => $payload['types'],
            'projects' => $payload['projects'],
            'currencies' => DualCurrency::CURRENCIES,
            'canManage' => $request->user()?->can('manageLedger', Vault::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('manageLedger', Vault::class);

        return Inertia::render('Vault/LedgerForm', [
            'mode' => 'create',
            'transaction' => [
                'direction' => request()->query('direction') === 'out' ? 'out' : 'in',
            ],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => DualCurrency::CURRENCIES,
            'inflowTypes' => Transaction::CASH_INFLOW_TYPES,
            'outflowTypes' => Transaction::CASH_OUTFLOW_TYPES,
            'available' => $this->liquidity->dualSnapshot(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'type' => ['nullable', 'string', Rule::in(Transaction::TYPES)],
            'reference_code' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->writes->create($data, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('vault.transactions')
            ->with('success', $data['direction'] === 'in' ? 'Money In recorded.' : 'Money Out recorded.');
    }

    public function edit(Transaction $transaction): Response
    {
        $this->authorize('manageLedger', Vault::class);

        $currency = (float) $transaction->amount_usd > 0 ? DualCurrency::USD : DualCurrency::IQD;

        return Inertia::render('Vault/LedgerForm', [
            'mode' => 'edit',
            'transaction' => [
                'id' => $transaction->id,
                'direction' => $transaction->direction ?: ($transaction->isCashOutflow() ? 'out' : 'in'),
                'currency' => $currency,
                'amount' => $currency === DualCurrency::USD
                    ? (float) $transaction->amount_usd
                    : (float) $transaction->amount_iqd,
                'occurred_on' => optional($transaction->occurred_on)->toDateString(),
                'description' => $transaction->description,
                'project_id' => $transaction->project_id,
                'type' => $transaction->type,
                'reference_code' => $transaction->reference_code,
            ],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => DualCurrency::CURRENCIES,
            'inflowTypes' => Transaction::CASH_INFLOW_TYPES,
            'outflowTypes' => Transaction::CASH_OUTFLOW_TYPES,
            'available' => $this->liquidity->dualSnapshot(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'type' => ['nullable', 'string', Rule::in(Transaction::TYPES)],
            'reference_code' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->writes->update($transaction, $data, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('vault.transactions')
            ->with('success', 'Ledger entry updated.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('manageLedger', Vault::class);

        $this->writes->softDelete($transaction, request()->user());

        return redirect()
            ->route('vault.transactions')
            ->with('success', 'Ledger entry soft-deleted; Available Cash rebuilt.');
    }
}
