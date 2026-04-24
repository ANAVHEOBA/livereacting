<?php

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderSceneLayersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'layer_ids' => ['required', 'array', 'min:1'],
            'layer_ids.*' => ['integer', 'distinct'],
        ];
    }
}
