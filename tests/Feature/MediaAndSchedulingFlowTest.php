<?php

use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

test('folders support hierarchy and file imports can be attached to nested folders', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $rootFolder = $this->postJson('/api/folders', [
        'name' => 'Videos',
        'type' => 'video',
    ])->assertCreated();

    $rootFolderId = $rootFolder->json('data.id');

    $childFolder = $this->postJson('/api/folders', [
        'name' => 'Weekly Shows',
        'parent_id' => $rootFolderId,
    ])->assertCreated()
        ->assertJsonPath('data.parent_id', $rootFolderId)
        ->assertJsonPath('data.type', 'video');

    $childFolderId = $childFolder->json('data.id');

    $this->getJson('/api/folders')
        ->assertOk()
        ->assertJsonCount(1, 'data.folders')
        ->assertJsonPath('data.folders.0.id', $rootFolderId);

    $this->getJson("/api/folders?parent_id={$rootFolderId}")
        ->assertOk()
        ->assertJsonCount(1, 'data.folders')
        ->assertJsonPath('data.folders.0.id', $childFolderId);

    $this->postJson('/api/files/import', [
        'source' => 'url',
        'source_url' => 'https://example.com/assets/show.mp4',
        'folder_id' => $childFolderId,
        'name' => 'Episode 1',
    ])->assertCreated()
        ->assertJsonPath('data.source', 'url')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.file.folder_id', $childFolderId)
        ->assertJsonPath('data.file.source', 'upload');
});

test('scheduled streams can be processed into live streams', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $destinationId = $this->postJson('/api/destinations', [
        'type' => 'rtmp',
        'name' => 'Channel RTMP',
        'rtmp_url' => 'https://example.com/live',
        'stream_key' => 'stream-key',
    ])->assertCreated()->json('data.id');

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Friday Stream',
        'description' => 'Weekly scheduled stream',
    ])->assertCreated()->json('data.id');

    $project = $this->getJson("/api/projects/{$projectId}?include=activeScene")
        ->assertOk();

    $activeSceneId = $project->json('data.active_scene.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'text',
        'content' => 'Going live shortly',
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $destinationId,
    ])->assertCreated();

    $startAt = Carbon::parse(now()->addMinute()->startOfSecond());

    $this->postJson("/api/projects/{$projectId}/schedule", [
        'start_at' => $startAt->toISOString(),
        'format' => '720p',
        'duration' => 3600,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'scheduled');

    $this->travelTo($startAt->copy()->addSecond());

    $this->artisan('streams:process-schedules')
        ->expectsOutputToContain('Started: 1')
        ->assertExitCode(0);

    $this->getJson("/api/projects/{$projectId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'live');

    $this->getJson("/api/projects/{$projectId}/schedules")
        ->assertOk()
        ->assertJsonPath('data.schedules.0.status', 'started');

    $this->travelBack();
});
