<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expense\RejectExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Vault;
use App\Services\ExpenseService;
use App\Services\LiquidityService;
use App\Support\DualCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly LiquidityService $liquidity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();

        if ($category !== '' && ! in_array($category, Expense::CATEGORIES, true)) {
            $category = '';
        }
        if ($status !== '' && ! in_array($status, Expense::STATUSES, true)) {
            $status = '';
        }

        $base = Expense::query();

        $statusCounts = (clone $base)
            ->selectRaw('approval_status, count(*) as aggregate')
            ->groupBy('approval_status')
            ->pluck('aggregate', 'approval_status');

        $totalsQuery = clone $base;
        if ($category !== '') {
            $totalsQuery->where('category', $category);
        }
        if ($status !== '') {
            $totalsQuery->where('approval_status', $status);
        }

        $spendTotals = [
            'amount_usd' => (float) (clone $totalsQuery)->sum('amount_usd'),
            'amount_iqd' => (float) (clone $totalsQuery)->sum('amount_iqd'),
            'count' => (int) (clone $totalsQuery)->count(),
        ];

        $query = Expense::query()
            ->with(['project:id,name', 'creator:id,name', 'document:id,path,original_name,mime_type'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($category !== '') {
            $query->where('category', $category);
        }
        if ($status !== '') {
            $query->where('approval_status', $status);
        }

        return Inertia::render('Expenses/Index', [
            'expenses' => $query->get(),
            'categories' => Expense::CATEGORIES,
            'statuses' => Expense::STATUSES,
            'filters' => [
                'category' => $category,
                'status' => $status,
            ],
            'statusCounts' => [
                'all' => (int) $base->count(),
                'pending' => (int) ($statusCounts[Expense::STATUS_PENDING] ?? 0),
                'held' => (int) ($statusCounts[Expense::STATUS_HELD] ?? 0),
                'approved' => (int) ($statusCounts[Expense::STATUS_APPROVED] ?? 0),
                'rejected' => (int) ($statusCounts[Expense::STATUS_REJECTED] ?? 0),
            ],
            'spendTotals' => $spendTotals,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('Expenses/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'vaults' => Vault::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Expense::CATEGORIES,
            'paymentMethods' => Expense::PAYMENT_METHODS,
            'currencies' => DualCurrency::CURRENCIES,
            'availableCash' => $this->liquidity->dualSnapshot(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        try {
            $expense = $this->expenses->create([
                ...$request->safe()->except('receipt'),
                'receipt' => $request->file('receipt'),
                'created_by' => $request->user()?->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', __('Expense created (pending ability to pay).'));
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        $expense->load(['project', 'vault', 'creator', 'approver', 'document', 'transaction']);
        $currency = strtoupper((string) ($expense->currency ?: DualCurrency::IQD));
        $amount = $currency === DualCurrency::USD
            ? (float) $expense->amount_usd
            : (float) $expense->amount_iqd;

        $ability = null;
        if ($expense->isAwaitingPayAbility()) {
            $ability = $this->liquidity->canPayCurrency(
                $expense->project,
                Payout::CATEGORY_EXPENSES,
                $currency,
                max($amount, 0.01),
                $expense->vault,
                excludeExpenseId: $expense->id,
            );
        }

        return Inertia::render('Expenses/Show', [
            'expense' => $expense,
            'categories' => Expense::CATEGORIES,
            'paymentMethods' => Expense::PAYMENT_METHODS,
            'availableCash' => $this->liquidity->dualSnapshot($expense->vault),
            'payAbility' => $ability,
        ]);
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        abort_unless($expense->isAwaitingPayAbility(), 403);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense->load(['project', 'document']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'vaults' => Vault::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Expense::CATEGORIES,
            'paymentMethods' => Expense::PAYMENT_METHODS,
            'currencies' => DualCurrency::CURRENCIES,
            'availableCash' => $this->liquidity->dualSnapshot(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        try {
            $this->expenses->update($expense, [
                ...$request->safe()->except('receipt'),
                'receipt' => $request->file('receipt'),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', __('Expense updated.'));
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);

        try {
            $this->expenses->approve($expense, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['approval_status' => $e->getMessage()]);
        }

        return back()->with('success', __('Expense approved; Available Cash updated.'));
    }

    public function hold(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('hold', $expense);

        $notes = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ])['notes'] ?? null;

        try {
            $this->expenses->hold($expense, $notes, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['approval_status' => $e->getMessage()]);
        }

        return back()->with('success', __('Expense held — ability to pay deferred.'));
    }

    public function reject(RejectExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('reject', $expense);

        try {
            $this->expenses->reject($expense, $request->validated('notes'), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['approval_status' => $e->getMessage()]);
        }

        return back()->with('success', __('Expense rejected.'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $this->expenses->softDelete($expense, request()->user());

        return redirect()
            ->route('expenses.index')
            ->with('success', __('Expense soft-deleted.'));
    }
}
