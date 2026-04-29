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
            'type' => ['nullable', 'string', 'in:video,audio,image'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'size_bytes' => ['nullable', 'integer', 'min:1'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'resolution' => ['nullable', 'string', 'max:50'],
            'format' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
