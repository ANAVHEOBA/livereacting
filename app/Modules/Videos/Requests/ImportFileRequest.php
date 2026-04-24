<?php

namespace App\Modules\Videos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'in:google_drive,dropbox,youtube,url,upload'],
            'source_url' => ['required', 'string', 'url'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
