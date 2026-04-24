<?php

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSceneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'transition' => ['nullable', Rule::in(['cut', 'fade', 'slide'])],
            'duration' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
