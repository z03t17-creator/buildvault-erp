<?php

namespace App\Http\Requests\Advance;

use App\Models\EmployeeAdvance;
use App\Support\DualCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $currency = strtoupper((string) ($this->input('currency') ?: DualCurrency::IQD));
        $amount = $this->input('amount');
        if ($amount === null || $amount === '') {
            $amount = $currency === DualCurrency::USD
                ? $this->input('amount_usd')
                : $this->input('amount_iqd');
        }

        $this->merge([
            'currency' => $currency,
            'amount' => $amount,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'exists:workers,id'],
            'project_id' => ['required', 'exists:projects,id'],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'advanced_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'repayment_method' => ['required', 'string', Rule::in(EmployeeAdvance::REPAYMENT_METHODS)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
