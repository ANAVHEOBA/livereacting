<?php

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartLiveStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::in(['720p', '1080p'])],
            'duration' => ['nullable', 'integer', 'min:60', 'max:86400'], // 1 min to 24 hours
            'scheduled_start_at' => ['nullable', 'date'],
        ];
    }
}
