<?php

namespace App\Modules\Destinations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'platform_id' => $this->platform_id,
            'is_valid' => $this->is_valid,
            'needs_reconnection' => $this->needsReconnection(),
            'rtmp_url' => $this->type === 'rtmp' ? $this->rtmp_url : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
