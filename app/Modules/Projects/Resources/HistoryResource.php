<?php

namespace App\Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
