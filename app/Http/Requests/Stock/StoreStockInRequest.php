<?php

namespace App\Http\Requests\Stock;

use App\Support\DualCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'moved_on' => ['required', 'date'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'currency' => ['nullable', Rule::in(DualCurrency::CURRENCIES)],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'purchase_price_usd' => ['nullable', 'numeric', 'min:0'],
            'payment_source' => ['nullable', Rule::in(\App\Models\StockMovement::PAYMENT_SOURCES)],
            'project_id' => [
                Rule::requiredIf(fn () => $this->input('payment_source') === \App\Models\StockMovement::PAYMENT_PROJECT_ADVANCE),
                'nullable',
                'integer',
                'exists:projects,id',
            ],
            'invoice_ref' => ['nullable', 'string', 'max:255'],
            'shelf_zone' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
