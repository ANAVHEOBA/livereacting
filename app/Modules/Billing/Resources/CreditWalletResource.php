<?php

namespace App\Modules\Billing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance' => $this->balance,
            'lifetime_earned' => $this->lifetime_earned,
            'lifetime_spent' => $this->lifetime_spent,
            'transactions' => CreditTransactionResource::collection($this->whenLoaded('transactions')),
        ];
    }
}
