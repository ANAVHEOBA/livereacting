<?php

namespace App\Modules\Interactive\Resources;

use App\Modules\Projects\Resources\SceneResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InteractiveElementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'scene_id' => $this->scene_id,
            'type' => $this->type,
            'name' => $this->name,
            'prompt' => $this->prompt,
            'status' => $this->status,
            'is_visible' => $this->is_visible,
            'sort_order' => $this->sort_order,
            'settings' => $this->settings ?? [],
            'results' => $this->results ?? [],
            'scene' => $this->whenLoaded('scene', fn () => new SceneResource($this->scene)),
            'responses' => InteractiveResponseResource::collection($this->whenLoaded('responses')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
