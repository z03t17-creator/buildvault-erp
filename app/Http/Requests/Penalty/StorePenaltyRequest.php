<?php

namespace App\Http\Requests\Penalty;

use App\Models\Penalty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'staff_id' => ['required', 'exists:staff,id'],
            'project_id' => ['required', 'exists:projects,id'],
            'floor_id' => ['nullable', 'exists:floors,id'],
            'type' => ['required', Rule::in(Penalty::TYPES)],
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_on' => ['required', 'date'],
            'amount_iqd' => ['required', 'numeric', 'gt:0'],
            'amount_usd' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
