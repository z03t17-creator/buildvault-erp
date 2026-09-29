<?php

namespace App\Http\Requests\Payout;

use App\Models\Payout;
use App\Support\DualCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $currency = strtoupper((string) ($this->input('currency') ?: DualCurrency::USD));
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
            'project_id' => ['required', 'exists:projects,id'],
            'worker_id' => ['nullable', 'exists:workers,id'],
            'floor_id' => ['nullable', 'exists:floors,id'],
            'vault_id' => ['nullable', 'exists:vaults,id'],
            'category' => ['required', 'string', Rule::in(Payout::CATEGORIES)],
            'currency' => ['required', Rule::in(DualCurrency::CURRENCIES)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'retention_holdback' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
