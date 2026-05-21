<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('backoffice_roles', 'slug')],
            'name' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:read,write,append'],
        ];
    }
}
