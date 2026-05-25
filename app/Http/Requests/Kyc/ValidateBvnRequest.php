<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class ValidateBvnRequest extends FormRequest
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
            'bvn' => ['required', 'string', 'digits:11'],
            'firstname' => ['required', 'string', 'max:120'],
            'lastname' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
