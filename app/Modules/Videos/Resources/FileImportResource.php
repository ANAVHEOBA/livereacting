<?php

namespace App\Modules\Videos\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'status' => $this->status,
            'progress' => $this->progress,
            'error_message' => $this->error_message,
            'file_id' => $this->file_id,
            'file' => $this->whenLoaded('file', function () {
                return new FileResource($this->file);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
