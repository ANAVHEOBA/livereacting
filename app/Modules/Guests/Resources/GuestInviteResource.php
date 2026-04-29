<?php

namespace App\Modules\Guests\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestInviteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'token' => $this->token,
            'status' => $this->status,
            'permissions' => $this->permissions ?? [],
            'invited_at' => $this->invited_at,
            'expires_at' => $this->expires_at,
            'joined_at' => $this->joined_at,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
