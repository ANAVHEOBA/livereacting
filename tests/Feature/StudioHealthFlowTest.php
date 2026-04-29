<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('studio health endpoint reports runtime readiness structure', function () {
    config()->set('streaming.ffmpeg.bin', '/definitely/missing/ffmpeg');
    config()->set('streaming.ffmpeg.ffprobe_bin', '/definitely/missing/ffprobe');

    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/studio/health')
        ->assertOk()
        ->assertJsonPath('data.engine.driver', config('streaming.engine.driver'))
        ->assertJsonPath('data.ffmpeg.exists', false)
        ->assertJsonPath('data.ffprobe.exists', false)
        ->assertJsonPath('data.runtime_storage.exists', true)
        ->assertJsonStructure([
            'data' => [
                'status',
                'checked_at',
                'engine',
                'ffmpeg',
                'ffprobe',
                'runtime_storage',
                'mediasoup',
                'providers',
            ],
        ]);
});
