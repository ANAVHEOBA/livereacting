<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\SocialAuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class SocialAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SocialAuthService $socialAuthService
    ) {}

    public function redirectToProvider(string $provider)
    {
        return $this->socialAuthService->redirectToProvider($provider);
    }

    public function handleProviderCallback(string $provider): JsonResponse
    {
        $result = $this->socialAuthService->handleProviderCallback($provider);

        if (!$result) {
            return $this->error('Failed to authenticate with ' . ucfirst($provider), 401);
        }

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Login successful');
    }
}
