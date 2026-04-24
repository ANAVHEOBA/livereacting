<?php

namespace App\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SceneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'transition' => $this->transition,
            'duration' => $this->duration,
            'sort_order' => $this->sort_order,
            'settings' => $this->settings ?? [],
            'is_active' => $this->relationLoaded('project')
                ? $this->project?->active_scene_id === $this->id
                : null,
            'layers' => $this->whenLoaded('layers', function () {
                return SceneLayerResource::collection($this->layers);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
