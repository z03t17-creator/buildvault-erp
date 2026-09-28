<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockOutRequest extends FormRequest
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
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'tower_id' => ['nullable', 'integer', 'exists:towers,id'],
            'floor_id' => ['nullable', 'integer', 'exists:floors,id'],
            'receiver' => ['nullable', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
