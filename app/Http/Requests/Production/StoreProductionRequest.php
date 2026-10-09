<?php

namespace App\Http\Requests\Production;

use App\Models\ProductionRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionRequest extends FormRequest
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
            'unit_type' => ['required', 'string', Rule::in(ProductionRecord::UNIT_TYPES)],
            'unit_label' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn () => $this->input('unit_type') === ProductionRecord::UNIT_OTHER),
            ],
            'assigned' => ['required', 'numeric', 'min:0'],
            'completed' => ['required', 'numeric', 'min:0'],
            'received' => ['nullable', 'numeric', 'min:0'],
            'recorded_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
