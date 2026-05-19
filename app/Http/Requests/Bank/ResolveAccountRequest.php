<?php

namespace App\Http\Requests\Bank;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAccountRequest extends FormRequest
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
            'account_number' => ['required', 'string', 'digits:10'],
            'bank_code' => ['required', 'string', 'max:10'],
        ];
    }
}
