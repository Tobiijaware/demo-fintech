<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CompleteRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'firstname' => ['sometimes', 'string', 'max:120'],
            'lastname' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'bvn' => ['sometimes', 'string', 'size:11'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }
}
