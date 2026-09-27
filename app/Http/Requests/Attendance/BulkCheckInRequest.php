<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class BulkCheckInRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'check_in' => ['required', 'date_format:H:i'],
            'floor_id' => ['nullable', 'exists:floors,id'],
            'worker_ids' => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['integer', 'exists:workers,id'],
        ];
    }
}
