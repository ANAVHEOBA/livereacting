<?php

namespace App\Modules\Billing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credits' => ['required', 'integer', 'min:1', 'max:100000'],
            'provider' => ['nullable', 'string', 'max:50'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
