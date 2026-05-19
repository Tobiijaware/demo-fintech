<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class ValidateNinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nin' => ['required', 'string', 'digits:11'],
        ];
    }
}
