<?php

namespace App\Http\Requests\Advance;

use App\Models\EmployeeAdvance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvanceRequest extends FormRequest
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
            'worker_id' => ['required', 'exists:workers,id'],
            'project_id' => ['required', 'exists:projects,id'],
            'amount_iqd' => ['required', 'numeric', 'gt:0'],
            'remaining_iqd' => ['nullable', 'numeric', 'min:0'],
            'advanced_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'repayment_method' => ['required', 'string', Rule::in(EmployeeAdvance::REPAYMENT_METHODS)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
