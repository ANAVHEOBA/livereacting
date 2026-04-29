<?php

namespace App\Modules\Billing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_code' => ['required', Rule::in(['starter', 'pro', 'studio'])],
            'billing_provider' => ['nullable', 'string', 'max:50'],
        ];
    }
}
