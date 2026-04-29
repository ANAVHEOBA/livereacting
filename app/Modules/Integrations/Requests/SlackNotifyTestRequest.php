<?php

namespace App\Modules\Integrations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SlackNotifyTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'connected_account_id' => ['required', 'integer', 'exists:connected_accounts,id'],
            'channel' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string', 'max:4000'],
            'blocks' => ['nullable', 'array'],
        ];
    }
}
