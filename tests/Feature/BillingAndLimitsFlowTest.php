<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('billing overview seeds plans wallet history and enforces import and guest limits', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/billing/plans')
        ->assertOk()
        ->assertJsonCount(3, 'data.plans');

    $this->getJson('/api/billing/overview')
        ->assertOk()
        ->assertJsonPath('data.subscription.plan.code', 'starter')
        ->assertJsonPath('data.wallet.balance', 250);

    $this->postJson('/api/billing/subscription', [
        'plan_code' => 'pro',
        'billing_provider' => 'stripe',
    ])->assertOk()
        ->assertJsonPath('data.plan.code', 'pro');

    $this->postJson('/api/billing/credits', [
        'credits' => 50,
        'provider' => 'stripe',
        'amount_cents' => 5000,
    ])->assertOk()
        ->assertJsonPath('data.balance', 1300);

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Limit Test',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/projects/{$projectId}/guests/room", [
        'max_guests' => 8,
    ])->assertStatus(400)
        ->assertJsonPath('message', 'Guest limit exceeds your Pro plan');

    $this->postJson('/api/billing/subscription', [
        'plan_code' => 'starter',
    ])->assertOk();

    $this->postJson('/api/files/import', [
        'source' => 'upload',
        'source_url' => 'https://example.com/assets/huge-master.mp4',
        'type' => 'video',
        'size_bytes' => 2147483648,
    ])->assertStatus(400)
        ->assertJsonPath('message', 'This asset exceeds your Starter plan video size limit');

    $this->getJson('/api/billing/history')
        ->assertOk()
        ->assertJsonCount(3, 'data.history');
});
