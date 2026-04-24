<?php

use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

test('project studio flow supports scenes layers validation live start and sync', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $projectResponse = $this->postJson('/api/projects', [
        'name' => 'Studio Project',
        'description' => 'Project with scenes and layers',
        'auto_sync' => true,
    ])->assertCreated()
        ->assertJsonPath('data.active_scene.name', 'Scene 1')
        ->assertJsonPath('data.scenes.0.is_active', true);

    $projectId = $projectResponse->json('data.id');
    $defaultSceneId = $projectResponse->json('data.active_scene.id');

    $videoImport = $this->postJson('/api/files/import', [
        'source' => 'url',
        'source_url' => 'https://example.com/assets/intro.mp4',
        'type' => 'video',
        'name' => 'Intro Reel',
    ])->assertCreated();

    $videoFileId = $videoImport->json('data.file.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$defaultSceneId}/layers", [
        'type' => 'video',
        'file_id' => $videoFileId,
    ])->assertCreated()
        ->assertJsonPath('data.type', 'video')
        ->assertJsonPath('data.file.id', $videoFileId);

    $textLayer = $this->postJson("/api/projects/{$projectId}/scenes/{$defaultSceneId}/layers", [
        'type' => 'text',
        'name' => 'Lower Third',
        'content' => 'Welcome to the show',
        'position' => ['x' => 48, 'y' => 920, 'width' => 820, 'height' => 96],
    ])->assertCreated();

    $textLayerId = $textLayer->json('data.id');

    $secondScene = $this->postJson("/api/projects/{$projectId}/scenes", [
        'name' => 'Countdown Scene',
        'transition' => 'fade',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Countdown Scene');

    $secondSceneId = $secondScene->json('data.id');

    $countdownEndsAt = Carbon::now()->addHour()->toIso8601String();

    $this->postJson("/api/projects/{$projectId}/scenes/{$secondSceneId}/layers", [
        'type' => 'countdown',
        'name' => 'Show Countdown',
        'settings' => ['ends_at' => $countdownEndsAt],
    ])->assertCreated()
        ->assertJsonPath('data.type', 'countdown');

    $this->postJson("/api/projects/{$projectId}/scenes/reorder", [
        'scene_ids' => [$secondSceneId, $defaultSceneId],
    ])->assertOk()
        ->assertJsonPath('data.scenes.0.id', $secondSceneId);

    $this->postJson("/api/projects/{$projectId}/scenes/{$secondSceneId}/activate")
        ->assertOk()
        ->assertJsonPath('data.id', $secondSceneId)
        ->assertJsonPath('data.is_active', true);

    $destinationId = $this->postJson('/api/destinations', [
        'type' => 'rtmp',
        'name' => 'Primary Destination',
        'rtmp_url' => 'https://example.com/live',
        'stream_key' => 'live-stream-key',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $destinationId,
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/validate")
        ->assertOk()
        ->assertJsonPath('data.valid', true);

    $this->postJson("/api/projects/{$projectId}/live", [
        'format' => '1080p',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'live')
        ->assertJsonPath('data.metadata.studio_snapshot.scene_count', 2)
        ->assertJsonPath('data.metadata.studio_snapshot.project.active_scene_id', $secondSceneId);

    $this->patchJson("/api/projects/{$projectId}/scenes/{$defaultSceneId}/layers/{$textLayerId}", [
        'content' => 'Updated lower third',
    ])->assertOk()
        ->assertJsonPath('data.content', 'Updated lower third');

    $this->postJson("/api/projects/{$projectId}/sync")
        ->assertOk()
        ->assertJsonPath('data.synced', true)
        ->assertJsonPath('data.changes.scene_count', 2)
        ->assertJsonPath('data.changes.active_scene_id', $secondSceneId)
        ->assertJsonPath('data.studio.project.active_scene_id', $secondSceneId);

    $this->getJson("/api/projects/{$projectId}?include=scenes,activeScene,destinations")
        ->assertOk()
        ->assertJsonPath('data.active_scene.id', $secondSceneId)
        ->assertJsonCount(2, 'data.scenes')
        ->assertJsonCount(1, 'data.destinations');
});
