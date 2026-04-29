<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Requests\PurchaseCreditsRequest;
use App\Modules\Billing\Requests\UpdateSubscriptionRequest;
use App\Modules\Billing\Resources\BillingEventResource;
use App\Modules\Billing\Resources\CreditWalletResource;
use App\Modules\Billing\Resources\SubscriptionPlanResource;
use App\Modules\Billing\Resources\UserSubscriptionResource;
use App\Modules\Billing\Services\BillingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected BillingService $billingService
    ) {}

    public function plans(): JsonResponse
    {
        return $this->success([
            'plans' => SubscriptionPlanResource::collection($this->billingService->getPlans()),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $overview = $this->billingService->getOverview($request->user());

        return $this->success([
            'subscription' => new UserSubscriptionResource($overview['subscription']),
            'wallet' => new CreditWalletResource($overview['wallet']),
            'usage' => $overview['usage'],
            'history' => BillingEventResource::collection($overview['history']),
        ]);
    }

    public function updateSubscription(UpdateSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->billingService->switchPlan(
            $request->user(),
            $request->validated()['plan_code'],
            $request->validated()['billing_provider'] ?? null
        );

        return $this->success(
            new UserSubscriptionResource($subscription->load('plan')),
            'Subscription updated successfully'
        );
    }

    public function purchaseCredits(PurchaseCreditsRequest $request): JsonResponse
    {
        $wallet = $this->billingService->purchaseCredits(
            $request->user(),
            $request->validated()['credits'],
            $request->validated()['provider'] ?? null,
            $request->validated()['amount_cents'] ?? null
        );

        return $this->success(
            new CreditWalletResource($wallet->load(['transactions' => fn ($query) => $query->latest()->limit(10)])),
            'Credits purchased successfully'
        );
    }

    public function history(Request $request): JsonResponse
    {
        $history = $this->billingService->getOverview($request->user())['history'];

        return $this->success([
            'history' => BillingEventResource::collection($history),
        ]);
    }
}
