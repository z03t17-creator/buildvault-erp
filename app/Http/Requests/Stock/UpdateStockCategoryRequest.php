<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockCategoryRequest extends FormRequest
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
        $categoryId = $this->route('stockCategory')?->id ?? $this->route('stockCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique('stock_categories', 'name')->ignore($categoryId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
