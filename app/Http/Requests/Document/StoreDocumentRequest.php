<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
        $type = (string) $this->input('type', Document::TYPE_OTHER);
        $mimes = implode(',', Document::mimesForType($type));

        return [
            'project_id' => ['required', 'exists:projects,id'],
            'worker_id' => ['nullable', 'exists:workers,id'],
            'type' => ['required', Rule::in(Document::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => [
                'required',
                'file',
                'max:'.Document::MAX_KILOBYTES,
                'mimes:'.$mimes,
            ],
        ];
    }
}
