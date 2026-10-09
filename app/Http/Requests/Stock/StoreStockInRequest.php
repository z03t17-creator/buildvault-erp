<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

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
            'purchase_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'invoice_ref' => ['nullable', 'string', 'max:255'],
            'shelf_zone' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
