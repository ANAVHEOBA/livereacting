<?php

namespace App\Modules\Interactive\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InteractiveResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'participant_name' => $this->participant_name,
            'response_key' => $this->response_key,
            'message' => $this->message,
            'is_correct' => $this->is_correct,
            'payload' => $this->payload ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
