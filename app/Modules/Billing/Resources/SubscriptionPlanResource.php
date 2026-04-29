<?php

namespace App\Modules\Billing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tier' => $this->tier,
            'price_monthly_cents' => $this->price_monthly_cents,
            'credits_included' => $this->credits_included,
            'max_storage_gb' => $this->max_storage_gb,
            'max_video_size_mb' => $this->max_video_size_mb,
            'max_destinations' => $this->max_destinations,
            'max_guests' => $this->max_guests,
            'max_stream_hours' => $this->max_stream_hours,
            'max_scenes' => $this->max_scenes,
            'max_interactive_elements' => $this->max_interactive_elements,
            'features' => $this->features ?? [],
        ];
    }
}
