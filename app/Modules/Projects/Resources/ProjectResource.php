<?php

namespace App\Modules\Projects\Resources;

use App\Modules\Destinations\Resources\DestinationResource;
use App\Modules\Guests\Resources\GuestRoomResource;
use App\Modules\Interactive\Resources\InteractiveElementResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'status' => $this->status,
            'auto_sync' => $this->auto_sync,
            'active_live_id' => $this->active_live_id,
            'active_scene_id' => $this->active_scene_id,
            'active_scene' => $this->whenLoaded('activeScene', function () {
                if ($this->activeScene) {
                    $this->activeScene->setRelation('project', $this->resource);
                }

                return new SceneResource($this->activeScene);
            }),
            'scenes' => $this->whenLoaded('scenes', function () {
                $this->scenes->each(function ($scene) {
                    $scene->setRelation('project', $this->resource);
                });

                return SceneResource::collection($this->scenes);
            }),
            'destinations' => $this->whenLoaded('destinations', function () {
                return DestinationResource::collection($this->destinations);
            }),
            'interactive_elements' => $this->whenLoaded('interactiveElements', function () {
                return InteractiveElementResource::collection($this->interactiveElements);
            }),
            'guest_room' => $this->whenLoaded('guestRoom', function () {
                return new GuestRoomResource($this->guestRoom);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
