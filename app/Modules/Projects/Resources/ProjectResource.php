<?php

namespace App\Modules\Projects\Resources;

use App\Modules\Destinations\Resources\DestinationResource;
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
            'destinations' => $this->whenLoaded('destinations', function () {
                return DestinationResource::collection($this->destinations);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
