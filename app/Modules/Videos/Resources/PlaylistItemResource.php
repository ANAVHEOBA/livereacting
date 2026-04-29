<?php

namespace App\Modules\Videos\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'start_offset_seconds' => $this->start_offset_seconds,
            'file' => new FileResource($this->whenLoaded('file')),
        ];
    }
}
