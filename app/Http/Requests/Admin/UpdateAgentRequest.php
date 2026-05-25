<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountTier;
use App\Enums\AgentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAgentRequest extends FormRequest
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
            'status' => ['sometimes', new Enum(AgentStatus::class)],
            'hub' => ['nullable', 'string', 'max:64'],
            'region' => ['nullable', 'string', 'max:64'],
            'tier' => ['sometimes', new Enum(AccountTier::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
