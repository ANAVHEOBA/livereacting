<?php

namespace App\Modules\Guests\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'slug' => $this->slug,
            'status' => $this->status,
            'max_guests' => $this->max_guests,
            'host_notes' => $this->host_notes,
            'settings' => $this->settings ?? [],
            'invites' => GuestInviteResource::collection($this->whenLoaded('invites')),
            'sessions' => GuestSessionResource::collection($this->whenLoaded('sessions')),
            'host_signaling' => $this->when(isset($this->host_signaling), $this->host_signaling),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
