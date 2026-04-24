<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('an authenticated user can create update validate and list destinations', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $createResponse = $this->postJson('/api/destinations', [
        'type' => 'rtmp',
        'name' => 'Main RTMP',
        'rtmp_url' => 'https://example.com/live',
        'stream_key' => 'secret-stream-key',
    ]);

    $createResponse->assertCreated()
        ->assertJsonPath('data.type', 'rtmp')
        ->assertJsonPath('data.is_valid', true);

    $destinationId = $createResponse->json('data.id');

    $this->patchJson("/api/destinations/{$destinationId}", [
        'name' => 'Primary RTMP',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Primary RTMP');

    $this->postJson("/api/destinations/{$destinationId}/validate")
        ->assertOk()
        ->assertJsonPath('data.valid', true);

    $this->getJson('/api/destinations')
        ->assertOk()
        ->assertJsonCount(1, 'data.destinations')
        ->assertJsonPath('data.destinations.0.id', $destinationId);
});
