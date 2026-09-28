<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockItemRequest extends FormRequest
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
        $itemId = $this->route('item')?->id ?? $this->route('item');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('stock_items', 'sku')->ignore($itemId),
            ],
            'category' => ['nullable', 'string', 'max:64'],
            'unit' => ['required', 'string', 'max:32'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'purchase_price_iqd' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
