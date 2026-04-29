<?php

namespace App\Modules\Guests\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guest_invite_id' => $this->guest_invite_id,
            'display_name' => $this->display_name,
            'role' => $this->role,
            'connection_status' => $this->connection_status,
            'media_state' => $this->media_state ?? [],
            'permissions' => $this->permissions ?? [],
            'last_seen_at' => $this->last_seen_at,
            'left_at' => $this->left_at,
        ];
    }
}
