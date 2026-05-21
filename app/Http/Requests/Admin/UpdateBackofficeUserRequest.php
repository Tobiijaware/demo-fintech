<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateBackofficeUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'firstname' => ['sometimes', 'string', 'max:80'],
            'lastname' => ['sometimes', 'string', 'max:80'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'backoffice_role_id' => ['sometimes', 'exists:backoffice_roles,id'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'hub' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', new Enum(UserStatus::class)],
        ];
    }
}
