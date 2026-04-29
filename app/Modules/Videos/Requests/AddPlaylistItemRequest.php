<?php

namespace App\Modules\Videos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPlaylistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_id' => ['required', 'integer'],
            'start_offset_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
