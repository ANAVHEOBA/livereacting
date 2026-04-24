<?php

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSceneLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['video', 'audio', 'image', 'text', 'countdown', 'overlay'])],
            'name' => ['nullable', 'string', 'max:255'],
            'file_id' => [
                'nullable',
                'integer',
                'exists:files,id',
                Rule::requiredIf(fn () => in_array($this->input('type'), ['video', 'audio', 'image'], true)),
            ],
            'content' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => in_array($this->input('type'), ['text', 'overlay'], true)),
            ],
            'is_visible' => ['nullable', 'boolean'],
            'position' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
