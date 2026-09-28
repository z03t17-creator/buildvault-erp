<?php

namespace App\Http\Requests\Advance;

use Illuminate\Foundation\Http\FormRequest;

class RepayAdvanceRequest extends FormRequest
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
            'amount_iqd' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
