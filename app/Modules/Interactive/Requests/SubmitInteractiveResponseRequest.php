<?php

namespace App\Modules\Interactive\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitInteractiveResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_name' => ['nullable', 'string', 'max:255'],
            'response_key' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
