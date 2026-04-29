<?php

namespace App\Modules\Guests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGuestInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['nullable', Rule::in(['guest', 'producer', 'cohost'])],
            'permissions' => ['nullable', 'array'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
