<?php

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date', 'after:now'],
            'format' => ['nullable', Rule::in(['720p', '1080p'])],
            'duration' => ['nullable', 'integer', 'min:60', 'max:86400'], // 1 min to 24 hours
        ];
    }

    public function messages(): array
    {
        return [
            'start_at.after' => 'Schedule start time must be in the future',
        ];
    }
}
