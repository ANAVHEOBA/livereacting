<?php

namespace App\Modules\Projects\Resources;

use App\Modules\Videos\Resources\FileResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SceneLayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scene_id' => $this->scene_id,
            'file_id' => $this->file_id,
            'type' => $this->type,
            'name' => $this->name,
            'content' => $this->content,
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'position' => $this->position ?? [],
            'settings' => $this->settings ?? [],
            'file' => $this->whenLoaded('file', function () {
                return new FileResource($this->file);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
