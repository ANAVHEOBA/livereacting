<?php

namespace App\Modules\Guests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuestInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'role' => ['sometimes', Rule::in(['guest', 'producer', 'cohost'])],
            'status' => ['sometimes', Rule::in(['pending', 'accepted', 'joined', 'revoked'])],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
