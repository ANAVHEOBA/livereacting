<?php

namespace App\Modules\Billing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'billing_provider' => $this->billing_provider,
            'billing_cycle' => $this->billing_cycle,
            'renews_at' => $this->renews_at,
            'trial_ends_at' => $this->trial_ends_at,
            'cancelled_at' => $this->cancelled_at,
            'metadata' => $this->metadata ?? [],
            'plan' => new SubscriptionPlanResource($this->whenLoaded('plan')),
        ];
    }
}
