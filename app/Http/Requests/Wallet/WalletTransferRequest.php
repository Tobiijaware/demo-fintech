<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class WalletTransferRequest extends FormRequest
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
            'account_number' => ['required', 'string', 'min:10', 'max:10'],
            'amount' => ['required', 'numeric', 'min:100'],
            'pin' => ['required', 'string', 'digits:4'],
            'remark' => ['nullable', 'string', 'max:200'],
        ];
    }
}
