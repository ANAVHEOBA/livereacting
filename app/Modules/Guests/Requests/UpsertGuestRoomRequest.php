<?php

namespace App\Modules\Guests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertGuestRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['draft', 'ready', 'live', 'closed'])],
            'max_guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'host_notes' => ['nullable', 'string', 'max:5000'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
