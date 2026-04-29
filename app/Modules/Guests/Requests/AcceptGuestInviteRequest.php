<?php

namespace App\Modules\Guests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptGuestInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'audio_enabled' => ['nullable', 'boolean'],
            'video_enabled' => ['nullable', 'boolean'],
        ];
    }
}
