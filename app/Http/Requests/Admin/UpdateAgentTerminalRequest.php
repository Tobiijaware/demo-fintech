<?php

namespace App\Http\Requests\Admin;

use App\Enums\AgentTerminalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAgentTerminalRequest extends FormRequest
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
            'status' => ['sometimes', new Enum(AgentTerminalStatus::class)],
            'model' => ['nullable', 'string', 'max:64'],
        ];
    }
}
