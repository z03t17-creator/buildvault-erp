<?php

namespace App\Http\Requests\Stock;

use App\Models\Floor;
use App\Models\Tower;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStockOutRequest extends FormRequest
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
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'moved_on' => ['required', 'date'],
            // Required for construction — drives project material cost rollup.
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'tower_id' => ['nullable', 'integer', 'exists:towers,id'],
            'floor_id' => ['nullable', 'integer', 'exists:floors,id'],
            'receiver' => ['nullable', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $projectId = $this->integer('project_id') ?: null;
            $towerId = $this->integer('tower_id') ?: null;
            $floorId = $this->integer('floor_id') ?: null;

            if ($towerId && $projectId) {
                $tower = Tower::query()->find($towerId);
                if (! $tower || (int) $tower->project_id !== $projectId) {
                    $validator->errors()->add('tower_id', __('Tower must belong to the selected project.'));
                }
            }

            if ($floorId) {
                $floor = Floor::query()->find($floorId);
                if (! $floor) {
                    return;
                }
                if ($towerId && (int) $floor->tower_id !== $towerId) {
                    $validator->errors()->add('floor_id', __('Floor must belong to the selected tower.'));
                }
                if ($projectId && ! $towerId) {
                    $tower = Tower::query()->find($floor->tower_id);
                    if (! $tower || (int) $tower->project_id !== $projectId) {
                        $validator->errors()->add('floor_id', __('Floor must belong to the selected project.'));
                    }
                }
            }
        });
    }
}
