<?php

namespace App\Modules\Videos\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'storage_path' => $this->storage_path,
            'size_bytes' => $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
            'resolution' => $this->resolution,
            'format' => $this->format,
            'status' => $this->status,
            'folder_id' => $this->folder_id,
            'folder' => $this->whenLoaded('folder', function () {
                return new FolderResource($this->folder);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
