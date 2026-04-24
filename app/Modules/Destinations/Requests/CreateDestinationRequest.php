<?php

namespace App\Modules\Destinations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['youtube', 'facebook', 'twitch', 'rtmp'])],
            'name' => ['required', 'string', 'max:255'],
            'platform_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'rtmp_url' => ['nullable', 'url', 'required_if:type,rtmp'],
            'stream_key' => ['nullable', 'string', 'max:2048', 'required_if:type,rtmp'],
            'token_expires_at' => ['nullable', 'date'],
        ];
    }
}
