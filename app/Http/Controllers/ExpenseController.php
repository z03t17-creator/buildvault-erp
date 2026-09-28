<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expense\RejectExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Vault;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::query()
            ->with(['project:id,name', 'creator:id,name', 'document:id,path,original_name,mime_type'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        return Inertia::render('Expenses/Index', [
            'expenses' => $query->get(),
            'categories' => Expense::CATEGORIES,
            'statuses' => Expense::STATUSES,
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
            return back()->withErrors(['amount_iqd' => $e->getMessage()]);
        }

        return redirect()
            ->route('expenses.show', $expense)
            ->with('success', __('Expense created (pending).'));
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        $expense->load(['project', 'vault', 'creator', 'approver', 'document', 'transaction']);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense,
            'categories' => Expense::CATEGORIES,
            'paymentMethods' => Expense::PAYMENT_METHODS,
        ]);
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        abort_unless($expense->isPending(), 403);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense->load(['project', 'document']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'vaults' => Vault::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Expense::CATEGORIES,
            'paymentMethods' => Expense::PAYMENT_METHODS,
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
            return back()->withErrors(['amount_iqd' => $e->getMessage()]);
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

        return back()->with('success', __('Expense approved; vault/pool debited.'));
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
}
