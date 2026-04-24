<?php

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSceneLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['video', 'audio', 'image', 'text', 'countdown', 'overlay'])],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file_id' => ['sometimes', 'nullable', 'integer', 'exists:files,id'],
            'content' => ['sometimes', 'nullable', 'string'],
            'is_visible' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'nullable', 'array'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
