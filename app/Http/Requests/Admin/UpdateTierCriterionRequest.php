<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTierCriterionRequest extends FormRequest
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
            'key' => ['sometimes', 'string', 'max:64'],
            'type' => ['sometimes', 'string', Rule::in([
                'text', 'phone', 'email', 'date', 'identity_bvn', 'identity_nin', 'document',
            ])],
            'label' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'required' => ['sometimes', 'boolean'],
            'group' => ['sometimes', 'string', 'max:32'],
            'rule_group' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
        ];
    }
}
