<?php

namespace App\Modules\Integrations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectedAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'email' => $this->email,
            'scopes' => $this->scopes ?? [],
            'metadata' => $this->metadata ?? [],
            'token_expires_at' => $this->token_expires_at,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
