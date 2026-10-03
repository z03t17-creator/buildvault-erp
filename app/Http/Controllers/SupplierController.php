<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()
            ->withCount('stockItems')
            ->orderBy('name')
            ->get();

        $overview = [
            'suppliers' => $suppliers->count(),
            'with_phone' => $suppliers->filter(fn (Supplier $s) => filled($s->phone))->count(),
            'with_email' => $suppliers->filter(fn (Supplier $s) => filled($s->email))->count(),
            'products_linked' => (int) $suppliers->sum('stock_items_count'),
        ];

        return Inertia::render('Stock/Suppliers/Index', [
            'suppliers' => $suppliers,
            'overview' => $overview,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Supplier::class);

        return Inertia::render('Stock/Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::query()->create($request->validated());

        return redirect()
            ->route('stock.suppliers.index')
            ->with('success', __('Supplier saved.'));
    }

    public function edit(Supplier $supplier): Response
    {
        $this->authorize('update', $supplier);

        return Inertia::render('Stock/Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return redirect()
            ->route('stock.suppliers.index')
            ->with('success', __('Supplier updated.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return redirect()
            ->route('stock.suppliers.index')
            ->with('success', __('Supplier deleted.'));
    }
}
