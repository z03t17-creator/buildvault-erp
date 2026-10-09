<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->user()?->can('update', $target) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'role' => ['required', 'string', Rule::in(Roles::ALL)],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'string', Rule::in(User::STATUSES)],
        ];
    }
}
