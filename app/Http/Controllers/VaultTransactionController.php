<?php

namespace App\Http\Controllers;

use App\Models\Vault;
use App\Services\VaultLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VaultTransactionController extends Controller
{
    public function __construct(
        private readonly VaultLedgerService $ledger,
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

        return Inertia::render('Vault/Transactions', [
            'vault' => [
                'id' => $vault->id,
                'name' => $vault->name,
            ],
            'balances' => [
                'current_iqd' => $balances['current_iqd'],
                'available_iqd' => $balances['available_iqd'],
                'reserved_iqd' => $balances['reserved_iqd'],
                'pending_iqd' => $balances['pending_iqd'],
                'ledger_cash_iqd' => $balances['ledger_cash_iqd'],
                'balance_matches_ledger' => $balances['balance_matches_ledger'],
            ],
            'transactions' => $payload['transactions'],
            'filters' => $payload['filters'],
            'types' => $payload['types'],
            'projects' => $payload['projects'],
        ]);
    }
}
