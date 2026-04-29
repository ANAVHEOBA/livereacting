<?php

namespace App\Modules\Integrations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportConnectedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'connected_account_id' => ['required', 'integer', 'exists:connected_accounts,id'],
            'asset_id' => ['required', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:video,audio,image'],
        ];
    }
}
