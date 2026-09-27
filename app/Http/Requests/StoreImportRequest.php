<?php

namespace App\Http\Requests;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(Import::TYPES)],
            'mode' => ['required', 'string', Rule::in(Import::MODES)],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx'],
        ];
    }
}
