<?php

namespace App\Modules\Interactive\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInteractiveElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scene_id' => ['sometimes', 'nullable', 'integer'],
            'type' => ['sometimes', Rule::in(['poll', 'trivia', 'countdown', 'chat_overlay', 'word_search', 'last_comment', 'hidden_object', 'featured_comment'])],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prompt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['draft', 'armed', 'live', 'archived'])],
            'is_visible' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
