<?php

namespace App\Modules\Billing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'type' => $this->type,
            'status' => $this->status,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'metadata' => $this->metadata ?? [],
            'occurred_at' => $this->occurred_at,
        ];
    }
}
