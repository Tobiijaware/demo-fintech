<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMakerCheckerPolicyRequest extends FormRequest
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
            'id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', Rule::unique('maker_checker_policies', 'id')],
            'department' => ['required', 'string', 'max:64'],
            'action' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:1000'],
            'resource' => ['nullable', 'string', 'max:64'],
            'threshold' => ['nullable', 'string', 'max:120'],
            'enforced' => ['sometimes', 'boolean'],
            'enforcement' => ['sometimes', 'in:live,policy'],
            'role_pairs' => ['required', 'array', 'min:1'],
            'role_pairs.*.id' => ['required', 'string', 'max:64'],
            'role_pairs.*.label' => ['required', 'string', 'max:120'],
            'role_pairs.*.maker_roles' => ['required', 'array', 'min:1'],
            'role_pairs.*.maker_roles.*' => ['string', 'max:64'],
            'role_pairs.*.checker_roles' => ['required', 'array', 'min:1'],
            'role_pairs.*.checker_roles.*' => ['string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
