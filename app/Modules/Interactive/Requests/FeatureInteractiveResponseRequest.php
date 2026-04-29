<?php

namespace App\Modules\Interactive\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeatureInteractiveResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response_id' => ['required', 'integer'],
        ];
    }
}
