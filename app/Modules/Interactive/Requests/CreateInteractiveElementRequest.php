<?php

namespace App\Modules\Interactive\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInteractiveElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scene_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(['poll', 'trivia', 'countdown', 'chat_overlay', 'word_search', 'last_comment', 'hidden_object', 'featured_comment'])],
            'name' => ['nullable', 'string', 'max:255'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['draft', 'armed', 'live', 'archived'])],
            'is_visible' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
