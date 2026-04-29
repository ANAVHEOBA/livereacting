<?php

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplySceneTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scene_template_id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
