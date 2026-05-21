<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountTier;
use App\Enums\ApplicantType;
use App\Enums\VerificationCheckStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingApplicationRequest extends FormRequest
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
            'applicant_type' => ['required', Rule::enum(ApplicantType::class)],
            'tier' => ['required', Rule::enum(AccountTier::class)],
            'verification_status' => ['nullable', Rule::enum(VerificationCheckStatus::class)],
            'business_name' => ['required', 'string', 'max:255'],
            'proprietor_name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'cac_number' => ['nullable', 'string', 'max:64'],
            'business_type' => ['nullable', 'string', 'max:128'],
            'bvn' => ['nullable', 'string', 'max:32'],
            'nin' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'estimated_settlement' => ['nullable', 'string', 'max:64'],
            'linked_agents' => ['nullable', 'array'],
        ];
    }
}
