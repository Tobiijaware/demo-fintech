<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMakerCheckerPolicyRequest extends FormRequest
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
            'department' => ['sometimes', 'string', 'max:64'],
            'action' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'resource' => ['nullable', 'string', 'max:64'],
            'threshold' => ['nullable', 'string', 'max:120'],
            'enforced' => ['sometimes', 'boolean'],
            'enforcement' => ['sometimes', 'in:live,policy'],
            'role_pairs' => ['sometimes', 'array', 'min:1'],
            'role_pairs.*.id' => ['required_with:role_pairs', 'string', 'max:64'],
            'role_pairs.*.label' => ['required_with:role_pairs', 'string', 'max:120'],
            'role_pairs.*.maker_roles' => ['required_with:role_pairs', 'array', 'min:1'],
            'role_pairs.*.maker_roles.*' => ['string', 'max:64'],
            'role_pairs.*.checker_roles' => ['required_with:role_pairs', 'array', 'min:1'],
            'role_pairs.*.checker_roles.*' => ['string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'id' => ['prohibited'],
        ];
    }
}
