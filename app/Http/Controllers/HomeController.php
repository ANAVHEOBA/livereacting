<?php

namespace App\Http\Controllers;

use App\Modules\Billing\Resources\SubscriptionPlanResource;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Projects\Services\StreamConfigService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
        protected StreamConfigService $streamConfigService
    ) {}

    public function __invoke(): Response
    {
        $studioConfig = $this->streamConfigService->getStudioConfig();

        return Inertia::render('Home', [
            'modules' => [
                [
                    'name' => 'Scenes and Layers',
                    'description' => 'Scene switching, layered composition, transitions, and reusable templates.',
                    'status' => 'Live',
                ],
                [
                    'name' => 'Media Library',
                    'description' => 'Imports, folders, playlists, transcoding metadata, and stream-ready assets.',
                    'status' => 'Live',
                ],
                [
                    'name' => 'Guest Rooms',
                    'description' => 'Mediasoup signaling, invite tokens, backstage state, and session control.',
                    'status' => 'Live',
                ],
                [
                    'name' => 'Interactive Stack',
                    'description' => 'Polls, trivia, countdowns, featured comments, and chat overlays.',
                    'status' => 'Live',
                ],
                [
                    'name' => 'Billing and Credits',
                    'description' => 'Plan catalog, credit wallets, billing history, and plan limit enforcement.',
                    'status' => 'Live',
                ],
                [
                    'name' => 'Streaming Runtime',
                    'description' => 'FFmpeg egress plus mediasoup guest ingress with TURN-aware signaling payloads.',
                    'status' => 'Live',
                ],
            ],
            'plans' => SubscriptionPlanResource::collection($this->billingService->getPlans()),
            'studioConfig' => $studioConfig,
            'checklist' => [
                'Mediasoup guest signaling server included under server/mediasoup',
                'Laravel APIs cover projects, scenes, videos, destinations, interactives, guests, billing, and studio config',
                'Feature tests cover the new product flows end to end',
            ],
        ]);
    }
}
