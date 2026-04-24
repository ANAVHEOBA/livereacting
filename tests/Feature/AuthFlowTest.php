<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

test('a user must verify their email before login succeeds', function () {
    $password = 'secret-password';

    $user = User::factory()->unverified()->create([
        'password' => Hash::make($password),
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertStatus(401);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addHour(),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ],
    );

    $this->getJson($verificationUrl)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('success', true);
});
