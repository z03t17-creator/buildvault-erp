<?php

namespace App\Http\Requests\Payout;

use App\Models\Payout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutRequest extends FormRequest
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
            'worker_id' => ['nullable', 'exists:workers,id'],
            'floor_id' => ['nullable', 'exists:floors,id'],
            'vault_id' => ['nullable', 'exists:vaults,id'],
            'category' => ['required', 'string', Rule::in(Payout::CATEGORIES)],
            'amount_usd' => ['required', 'numeric', 'gt:0'],
            'retention_holdback' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
