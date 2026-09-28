<?php

namespace App\Http\Requests\Worker;

use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkerRequest extends FormRequest
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
            'project_id' => ['nullable', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::in(Worker::ROLES)],
            'daily_rate_usd' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate_usd' => ['nullable', 'numeric', 'min:0'],
            'manual_ot_hours' => ['nullable', 'numeric', 'min:0'],
            'spending_limit_usd' => ['nullable', 'numeric', 'min:0'],
            'phone' => ['nullable', 'string', 'max:50'],
            'national_id_number' => ['nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ];
    }
}
