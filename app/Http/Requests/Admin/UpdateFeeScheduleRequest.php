<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeScheduleRequest extends FormRequest
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
            'product_label' => ['sometimes', 'string', 'max:128'],
            'fee_type' => ['sometimes', 'string', 'in:flat,percent'],
            'rate_or_amount' => ['sometimes', 'numeric', 'min:0'],
            'effective_from' => ['sometimes', 'date'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
