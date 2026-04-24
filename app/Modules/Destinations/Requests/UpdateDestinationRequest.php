<?php

namespace App\Modules\Destinations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'platform_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'rtmp_url' => ['nullable', 'url'],
            'stream_key' => ['nullable', 'string', 'max:2048'],
            'token_expires_at' => ['nullable', 'date'],
        ];
    }
}
