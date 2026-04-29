<?php

use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('streaming.engine.driver', 'fake');
});

test('starting a live stream in fake worker mode writes runtime artifacts and worker metadata', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Worker Runtime Project',
        'description' => 'Worker metadata verification',
    ])->assertCreated()->json('data.id');

    $project = $this->getJson("/api/projects/{$projectId}?include=activeScene")->assertOk();
    $activeSceneId = $project->json('data.active_scene.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'text',
        'content' => 'Opening slate',
    ])->assertCreated();

    $destinationId = $this->postJson('/api/destinations', [
        'type' => 'rtmp',
        'name' => 'Primary RTMP',
        'rtmp_url' => 'rtmp://example.com/live',
        'stream_key' => 'stream-key',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $destinationId,
    ])->assertCreated();

    $response = $this->postJson("/api/projects/{$projectId}/live", [
        'format' => '720p',
    ])->assertCreated()
        ->assertJsonPath('data.metadata.worker.driver', 'fake')
        ->assertJsonPath('data.metadata.worker.status', 'running');

    $liveStreamId = $response->json('data.id');
    $runtimePath = storage_path('app/stream-workers/'.$liveStreamId);

    expect(File::exists($runtimePath.'/overlay.txt'))->toBeTrue();
    expect(File::exists($runtimePath.'/studio.json'))->toBeTrue();
    expect(File::exists($runtimePath.'/manifest.json'))->toBeTrue();

    $overlayText = File::get($runtimePath.'/overlay.txt');

    expect($overlayText)->toContain('Worker Runtime Project');
    expect($overlayText)->toContain('Opening slate');
});

test('syncing after the active scene source changes restarts the fake worker runtime', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $projectResponse = $this->postJson('/api/projects', [
        'name' => 'Restart Runtime Project',
        'description' => 'Worker restart verification',
    ])->assertCreated();

    $projectId = $projectResponse->json('data.id');
    $defaultSceneId = $projectResponse->json('data.active_scene.id');

    $firstImport = $this->postJson('/api/files/import', [
        'source' => 'url',
        'source_url' => 'https://example.com/assets/intro-a.mp4',
        'type' => 'video',
        'name' => 'Intro A',
    ])->assertCreated();

    $secondImport = $this->postJson('/api/files/import', [
        'source' => 'url',
        'source_url' => 'https://example.com/assets/intro-b.mp4',
        'type' => 'video',
        'name' => 'Intro B',
    ])->assertCreated();

    $firstFileId = $firstImport->json('data.file.id');
    $secondFileId = $secondImport->json('data.file.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$defaultSceneId}/layers", [
        'type' => 'video',
        'file_id' => $firstFileId,
    ])->assertCreated();

    $sceneTwo = $this->postJson("/api/projects/{$projectId}/scenes", [
        'name' => 'Scene B',
    ])->assertCreated();

    $sceneTwoId = $sceneTwo->json('data.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$sceneTwoId}/layers", [
        'type' => 'video',
        'file_id' => $secondFileId,
    ])->assertCreated();

    $destinationId = $this->postJson('/api/destinations', [
        'type' => 'rtmp',
        'name' => 'Primary RTMP',
        'rtmp_url' => 'rtmp://example.com/live',
        'stream_key' => 'stream-key',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $destinationId,
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/live", [
        'format' => '720p',
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/scenes/{$sceneTwoId}/activate")
        ->assertOk();

    $syncResponse = $this->postJson("/api/projects/{$projectId}/sync")
        ->assertOk()
        ->assertJsonPath('data.synced', true)
        ->assertJsonPath('data.changes.worker_restart_required', true);

    $liveStream = LiveStream::query()->latest()->first();

    expect($liveStream->metadata['worker']['restart_count'])->toBe(1);
    expect($liveStream->metadata['worker']['source_signature'])->toContain((string) $secondFileId);

    $overlayText = File::get(storage_path('app/stream-workers/'.$liveStream->id.'/overlay.txt'));

    expect($overlayText)->toContain('Scene B');
    expect($syncResponse->json('data.live_stream_id'))->toBe($liveStream->id);
});

test('the fake worker builds a multi-layer ffmpeg composition plan', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $projectResponse = $this->postJson('/api/projects', [
        'name' => 'Composition Runtime Project',
        'description' => 'Multi-layer composition verification',
    ])->assertCreated();

    $projectId = $projectResponse->json('data.id');
    $activeSceneId = $projectResponse->json('data.active_scene.id');

    $videoFileId = $this->postJson('/api/files/import', [
        'source' => 'url',
        'source_url' => 'https://example.com/assets/background.mp4',
        'type' => 'video',
        'name' => 'Background Video',
    ])->assertCreated()->json('data.file.id');

    $imageFileId = $this->postJson('/api/files/import', [
        'source' => 'url',
        'source_url' => 'https://example.com/assets/logo.png',
        'type' => 'image',
        'name' => 'Logo',
    ])->assertCreated()->json('data.file.id');

    $audioFileId = $this->postJson('/api/files/import', [
        'source' => 'url',
        'source_url' => 'https://example.com/assets/bed.mp3',
        'type' => 'audio',
        'name' => 'Music Bed',
    ])->assertCreated()->json('data.file.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'video',
        'file_id' => $videoFileId,
        'position' => ['x' => 0, 'y' => 0, 'width' => 1280, 'height' => 720],
        'settings' => ['fit' => 'cover'],
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'image',
        'file_id' => $imageFileId,
        'position' => ['x' => 920, 'y' => 48, 'width' => 280, 'height' => 140],
        'settings' => ['fit' => 'contain', 'opacity' => 0.85],
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'text',
        'content' => 'Now Streaming',
        'position' => ['x' => 60, 'y' => 560, 'width' => 540, 'height' => 96],
        'settings' => [
            'font_size' => 48,
            'background_color' => '#0f172a',
            'background_opacity' => 0.7,
            'padding' => 18,
        ],
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'countdown',
        'name' => 'Show Countdown',
        'position' => ['x' => 930, 'y' => 560, 'width' => 250, 'height' => 96],
        'settings' => [
            'ends_at' => now()->addMinutes(15)->toIso8601String(),
            'background_color' => '#111827',
            'background_opacity' => 0.8,
            'padding' => 18,
        ],
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'audio',
        'file_id' => $audioFileId,
        'settings' => ['volume' => 0.35],
    ])->assertCreated();

    $destinationId = $this->postJson('/api/destinations', [
        'type' => 'rtmp',
        'name' => 'Primary RTMP',
        'rtmp_url' => 'rtmp://example.com/live',
        'stream_key' => 'stream-key',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $destinationId,
    ])->assertCreated();

    $response = $this->postJson("/api/projects/{$projectId}/live", [
        'format' => '720p',
    ])->assertCreated();

    $command = $response->json('data.metadata.worker.command');
    $filterComplex = $command[array_search('-filter_complex', $command, true) + 1];

    expect(substr_count($filterComplex, 'overlay='))->toBeGreaterThanOrEqual(2);
    expect($filterComplex)->toContain('drawbox=');
    expect($filterComplex)->toContain('textfile=');
    expect($filterComplex)->toContain('aresample=48000,volume=0.35');
    expect($filterComplex)->toContain('%{eif\\:max(floor((');
    expect($response->json('data.metadata.worker.render_signature'))->not->toBeEmpty();
    expect(File::exists(storage_path('app/stream-workers/'.$response->json('data.id').'/command.json')))->toBeTrue();
});
