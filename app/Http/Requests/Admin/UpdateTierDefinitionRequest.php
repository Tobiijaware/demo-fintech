<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTierDefinitionRequest extends FormRequest
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
            'label' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
            'limits' => ['sometimes', 'array'],
            'limits.daily_transfer' => ['sometimes', 'numeric', 'min:0'],
            'limits.single_transfer' => ['sometimes', 'numeric', 'min:0'],
            'limits.balance_limit' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
