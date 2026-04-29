<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('interactive elements and guest invites work end to end', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Interactive Guest Show',
    ])->assertCreated()->json('data.id');

    $poll = $this->postJson("/api/projects/{$projectId}/interactive", [
        'type' => 'poll',
        'name' => 'Pick the next segment',
        'prompt' => 'What should play next?',
        'settings' => [
            'options' => [
                ['id' => 'intro', 'label' => 'Intro'],
                ['id' => 'trivia', 'label' => 'Trivia'],
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.type', 'poll');

    $pollId = $poll->json('data.id');

    $response = $this->postJson("/api/projects/{$projectId}/interactive/{$pollId}/responses", [
        'participant_name' => 'Ari',
        'response_key' => 'trivia',
    ])->assertCreated()
        ->assertJsonPath('data.response_key', 'trivia');

    $responseId = $response->json('data.id');

    $this->postJson("/api/projects/{$projectId}/interactive/{$pollId}/feature", [
        'response_id' => $responseId,
    ])->assertOk()
        ->assertJsonPath('data.results.featured_response.response_key', 'trivia');

    $this->postJson("/api/projects/{$projectId}/interactive/{$pollId}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'live')
        ->assertJsonPath('data.is_visible', true);

    $room = $this->postJson("/api/projects/{$projectId}/guests/room", [
        'max_guests' => 2,
        'host_notes' => 'Join backstage 10 minutes early',
    ])->assertOk()
        ->assertJsonPath('data.max_guests', 2)
        ->assertJsonPath('data.host_signaling.room_slug', 'interactive-guest-show-'.$projectId);

    $invite = $this->postJson("/api/projects/{$projectId}/guests/invites", [
        'name' => 'Jordan',
        'email' => 'jordan@example.com',
        'permissions' => [
            'can_publish_audio' => true,
            'can_publish_video' => true,
        ],
    ])->assertCreated();

    $inviteId = $invite->json('data.id');
    $inviteToken = $invite->json('data.token');

    $this->getJson("/api/guest-invites/{$inviteToken}")
        ->assertOk()
        ->assertJsonPath('data.invite.id', $inviteId)
        ->assertJsonPath('data.room.slug', 'interactive-guest-show-'.$projectId);

    $accepted = $this->postJson("/api/guest-invites/{$inviteToken}/accept", [
        'display_name' => 'Jordan Camera',
    ])->assertOk()
        ->assertJsonPath('data.session.display_name', 'Jordan Camera')
        ->assertJsonPath('data.signaling.room_slug', 'interactive-guest-show-'.$projectId);

    $sessionId = $accepted->json('data.session.id');

    $this->patchJson("/api/projects/{$projectId}/guests/sessions/{$sessionId}", [
        'connection_status' => 'live',
        'media_state' => ['screen_enabled' => true],
    ])->assertOk()
        ->assertJsonPath('data.connection_status', 'live')
        ->assertJsonPath('data.media_state.screen_enabled', true);

    $this->getJson("/api/projects/{$projectId}/guests")
        ->assertOk()
        ->assertJsonPath('data.room.sessions.0.id', $sessionId);
});
