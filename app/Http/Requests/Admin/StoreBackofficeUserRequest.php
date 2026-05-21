<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreBackofficeUserRequest extends FormRequest
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
            'firstname' => ['required', 'string', 'max:80'],
            'lastname' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'backoffice_role_id' => ['required', 'exists:backoffice_roles,id'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'hub' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', new Enum(UserStatus::class)],
        ];
    }
}
