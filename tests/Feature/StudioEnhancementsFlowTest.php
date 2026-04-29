<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('playlists scene templates and studio config are exposed through the api', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Template Studio',
    ])->assertCreated()->json('data.id');

    $project = $this->getJson("/api/projects/{$projectId}?include=activeScene")
        ->assertOk();

    $sceneId = $project->json('data.active_scene.id');

    $fileImport = $this->postJson('/api/files/import', [
        'source' => 'google_drive',
        'source_url' => 'https://drive.google.com/file/d/demo/view',
        'type' => 'video',
        'name' => 'Drive Reel',
        'metadata' => ['folder' => 'Season 2'],
    ])->assertCreated()
        ->assertJsonPath('data.file.metadata.provider', 'google_drive');

    $fileId = $fileImport->json('data.file.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$sceneId}/layers", [
        'type' => 'video',
        'file_id' => $fileId,
    ])->assertCreated();

    $playlist = $this->postJson('/api/playlists', [
        'project_id' => $projectId,
        'name' => 'Show Openers',
    ])->assertCreated()
        ->assertJsonPath('data.project_id', $projectId);

    $playlistId = $playlist->json('data.id');

    $this->postJson("/api/playlists/{$playlistId}/items", [
        'file_id' => $fileId,
    ])->assertOk()
        ->assertJsonCount(1, 'data.items');

    $template = $this->postJson("/api/projects/{$projectId}/scene-templates", [
        'scene_id' => $sceneId,
        'name' => 'Video Base Template',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Video Base Template');

    $templateId = $template->json('data.id');

    $this->postJson("/api/projects/{$projectId}/scene-templates/apply", [
        'scene_template_id' => $templateId,
        'name' => 'Applied Template Scene',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Applied Template Scene');

    $this->getJson('/api/scene-templates')
        ->assertOk()
        ->assertJsonCount(1, 'data.scene_templates');

    $this->getJson('/api/studio/config')
        ->assertOk()
        ->assertJsonPath('data.mediasoup.enabled', true)
        ->assertJsonPath('data.assets.playlist_enabled', true);

    $this->getJson("/api/projects/{$projectId}?include=scenes,interactiveElements,guestRoom")
        ->assertOk()
        ->assertJsonCount(2, 'data.scenes');
});
