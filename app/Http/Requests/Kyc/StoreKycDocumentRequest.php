<?php

namespace App\Http\Requests\Kyc;

use App\Enums\OnboardingDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKycDocumentRequest extends FormRequest
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
            'document_type' => ['required', Rule::enum(OnboardingDocumentType::class)],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
