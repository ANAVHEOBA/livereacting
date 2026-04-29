<?php

namespace App\Modules\Guests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuestSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'connection_status' => ['sometimes', Rule::in(['joined', 'backstage', 'live', 'offline'])],
            'media_state' => ['sometimes', 'array'],
            'permissions' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
