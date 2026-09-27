<?php

namespace App\Http\Requests\Penalty;

use Illuminate\Foundation\Http\FormRequest;

class StorePenaltyRequest extends FormRequest
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
            'floor_id' => ['nullable', 'exists:floors,id'],
            'payout_id' => ['nullable', 'exists:payouts,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'amount_usd' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
