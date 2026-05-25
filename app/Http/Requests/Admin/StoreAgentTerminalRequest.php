<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentTerminalRequest extends FormRequest
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
            'serial_number' => ['required', 'string', 'max:64', 'unique:agent_terminals,serial_number'],
            'model' => ['nullable', 'string', 'max:64'],
        ];
    }
}
