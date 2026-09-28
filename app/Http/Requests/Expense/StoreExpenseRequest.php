<?php

namespace App\Http\Requests\Expense;

use App\Models\Document;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'project_id' => ['required', 'exists:projects,id'],
            'category' => ['required', 'string', Rule::in(Expense::CATEGORIES)],
            'amount_iqd' => ['required', 'numeric', 'gt:0'],
            'expense_date' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', Rule::in(Expense::PAYMENT_METHODS)],
            'description' => ['nullable', 'string'],
            'vault_id' => ['nullable', 'exists:vaults,id'],
            'receipt' => [
                'nullable',
                'file',
                'max:'.Document::MAX_KILOBYTES,
                'mimes:'.implode(',', Document::mimesForType(Document::TYPE_RECEIPT)),
            ],
        ];
    }
}
