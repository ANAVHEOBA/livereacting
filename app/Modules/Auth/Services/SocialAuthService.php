<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Repositories\UserRepository;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialAuthService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {}

    public function redirectToProvider(string $provider): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback(string $provider): ?array
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            
            $user = $this->findOrCreateUser($socialUser, $provider);
            
            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function findOrCreateUser(SocialiteUser $socialUser, string $provider): User
    {
        // Check if user exists with this provider ID
        $user = $this->userRepository->findByGoogleId($socialUser->getId());

        if ($user) {
            return $user;
        }

        // Check if user exists with this email
        $user = $this->userRepository->findByEmail($socialUser->getEmail());

        if ($user) {
            // Link the social account to existing user
            $this->userRepository->update($user, [
                'google_id' => $socialUser->getId(),
            ]);
            return $user;
        }

        // Create new user
        return $this->userRepository->create([
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            'google_id' => $socialUser->getId(),
            'email_verified_at' => now(), // Auto-verify for social login
        ]);
    }
}
