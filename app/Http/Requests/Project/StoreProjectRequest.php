<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'contract_number' => ['nullable', 'string', 'max:255'],
            'total_budget_usd' => ['nullable', 'numeric', 'min:0'],
            'contract_value_iqd' => ['nullable', 'numeric', 'min:0'],
            'budget_iqd' => ['nullable', 'numeric', 'min:0'],
            'allocation_expenses_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocation_payroll_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocation_insurance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocation_penalty_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocation_profit_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', Rule::in(Project::STATUSES)],
        ];
    }
}
