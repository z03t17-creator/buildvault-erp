<?php

namespace App\Http\Requests\Stock;

use App\Support\DualCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('currency')) {
            $this->merge(['currency' => DualCurrency::IQD]);
        }
        if (! $this->filled('purchase_price') && $this->filled('purchase_price_iqd')) {
            $this->merge([
                'currency' => DualCurrency::IQD,
                'purchase_price' => $this->input('purchase_price_iqd'),
            ]);
        }
        if (! $this->filled('purchase_price') && $this->filled('purchase_price_usd')) {
            $this->merge([
                'currency' => DualCurrency::USD,
                'purchase_price' => $this->input('purchase_price_usd'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', 'unique:stock_items,sku'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:stock_items,barcode'],
            'category' => ['nullable', 'string', 'max:64'],
            'stock_category_id' => ['nullable', 'integer', 'exists:stock_categories,id'],
            'unit' => ['required', 'string', 'max:32'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'purchase_price_usd' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'auto_sku' => ['sometimes', 'boolean'],
        ];
    }
}
