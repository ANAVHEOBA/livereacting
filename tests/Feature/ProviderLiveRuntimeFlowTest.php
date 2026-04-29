<?php

use App\Models\ConnectedAccount;
use App\Models\LiveStream;
use App\Models\StreamingDestination;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

test('youtube destinations are provisioned natively when a stream starts and completed when it stops', function () {
    config()->set('services.twitch.client_id', 'twitch-client');

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Launch Show',
        'description' => 'Native YouTube broadcast provisioning',
    ])->assertCreated()->json('data.id');

    $project = $this->getJson("/api/projects/{$projectId}?include=activeScene")->assertOk();
    $activeSceneId = $project->json('data.active_scene.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'text',
        'content' => 'Live in 3...2...1',
    ])->assertCreated();

    $account = ConnectedAccount::create([
        'user_id' => $user->id,
        'provider' => 'youtube',
        'external_id' => 'UC123',
        'name' => 'Studio Channel',
        'email' => 'studio@example.com',
        'access_token' => 'youtube-access-token',
        'refresh_token' => 'youtube-refresh-token',
        'scopes' => ['https://www.googleapis.com/auth/youtube'],
    ]);

    $destination = StreamingDestination::create([
        'user_id' => $user->id,
        'connected_account_id' => $account->id,
        'type' => 'youtube',
        'name' => 'Studio Channel',
        'platform_id' => 'UC123',
        'access_token' => 'youtube-access-token',
        'refresh_token' => 'youtube-refresh-token',
        'is_valid' => true,
    ]);

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $destination->id,
    ])->assertCreated();

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'liveStreams?part=id,snippet,cdn,contentDetails,status') => Http::response([
                'id' => 'yt-stream-1',
                'cdn' => [
                    'ingestionInfo' => [
                        'ingestionAddress' => 'rtmp://a.rtmp.youtube.com/live2',
                        'streamName' => 'yt-stream-key',
                    ],
                ],
            ], 200),
            str_contains($url, 'liveBroadcasts?part=id,snippet,contentDetails,status') => Http::response([
                'id' => 'yt-broadcast-1',
                'status' => [
                    'lifeCycleStatus' => 'created',
                ],
            ], 200),
            str_contains($url, 'liveBroadcasts/bind?id=yt-broadcast-1') => Http::response([
                'id' => 'yt-broadcast-1',
                'status' => [
                    'lifeCycleStatus' => 'ready',
                ],
            ], 200),
            str_contains($url, 'liveBroadcasts/transition?id=yt-broadcast-1&broadcastStatus=complete') => Http::response([
                'id' => 'yt-broadcast-1',
                'status' => [
                    'lifeCycleStatus' => 'complete',
                ],
            ], 200),
            default => Http::response([], 404),
        };
    });

    $this->postJson("/api/projects/{$projectId}/live", [
        'format' => '1080p',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'live')
        ->assertJsonPath('data.metadata.destination_sessions.0.provider', 'youtube')
        ->assertJsonPath('data.metadata.destination_sessions.0.broadcast_id', 'yt-broadcast-1')
        ->assertJsonPath('data.metadata.egress.outputs.0.full_rtmp_url', 'rtmp://a.rtmp.youtube.com/live2/yt-stream-key');

    $this->deleteJson("/api/projects/{$projectId}/live")
        ->assertOk();

    $liveStream = LiveStream::query()->latest()->first();

    expect($liveStream->status)->toBe('stopped');
    expect($liveStream->metadata['destination_finalization'][0]['provider'])->toBe('youtube');
    expect($liveStream->metadata['destination_finalization'][0]['status'])->toBe('completed');
});

test('meta and twitch destinations receive provider-specific egress targets when a stream starts', function () {
    config()->set('services.twitch.client_id', 'twitch-client');
    config()->set('services.meta.graph_version', 'v22.0');

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $projectId = $this->postJson('/api/projects', [
        'name' => 'Crosspost Show',
        'description' => 'Meta and Twitch provisioning',
    ])->assertCreated()->json('data.id');

    $project = $this->getJson("/api/projects/{$projectId}?include=activeScene")->assertOk();
    $activeSceneId = $project->json('data.active_scene.id');

    $this->postJson("/api/projects/{$projectId}/scenes/{$activeSceneId}/layers", [
        'type' => 'text',
        'content' => 'Crosspost runtime',
    ])->assertCreated();

    $twitchAccount = ConnectedAccount::create([
        'user_id' => $user->id,
        'provider' => 'twitch',
        'external_id' => 'tw-1',
        'name' => 'Studio Twitch',
        'access_token' => 'twitch-access-token',
        'refresh_token' => 'twitch-refresh-token',
        'scopes' => ['user:read:email', 'channel:read:stream_key'],
    ]);

    $twitchDestination = StreamingDestination::create([
        'user_id' => $user->id,
        'connected_account_id' => $twitchAccount->id,
        'type' => 'twitch',
        'name' => 'Studio Twitch',
        'platform_id' => 'tw-1',
        'access_token' => 'twitch-access-token',
        'refresh_token' => 'twitch-refresh-token',
        'is_valid' => true,
    ]);

    $metaAccount = ConnectedAccount::create([
        'user_id' => $user->id,
        'provider' => 'meta',
        'external_id' => 'meta-user-1',
        'name' => 'Meta Studio',
        'access_token' => 'meta-user-token',
        'scopes' => ['pages_show_list', 'pages_manage_posts', 'publish_video'],
    ]);

    $metaDestination = StreamingDestination::create([
        'user_id' => $user->id,
        'connected_account_id' => $metaAccount->id,
        'type' => 'facebook',
        'name' => 'Main Page',
        'platform_id' => 'page-1',
        'access_token' => 'page-access-token',
        'is_valid' => true,
    ]);

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $twitchDestination->id,
    ])->assertCreated();

    $this->postJson("/api/projects/{$projectId}/destinations", [
        'destination_id' => $metaDestination->id,
    ])->assertCreated();

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            $url === 'https://api.twitch.tv/helix/streams/key?broadcaster_id=tw-1' => Http::response([
                'data' => [[
                    'stream_key' => 'live_user_123',
                ]],
            ], 200),
            $url === 'https://ingest.twitch.tv/ingests' => Http::response([
                'ingests' => [[
                    'default' => true,
                    'name' => 'US West: San Francisco, CA',
                    'url_template' => 'rtmp://sfo.contribute.live-video.net/app/{stream_key}',
                ]],
            ], 200),
            $url === 'https://graph.facebook.com/v22.0/page-1/live_videos' => Http::response([
                'id' => 'fb-live-1',
                'secure_stream_url' => 'rtmps://rtmp.facebook.com:443/rtmp/fb-stream-key?ds=1&a=token',
                'permalink_url' => 'https://facebook.com/live/fb-live-1',
                'embed_html' => '<iframe></iframe>',
            ], 200),
            $url === 'https://graph.facebook.com/v22.0/fb-live-1/live_videos' => Http::response([
                'id' => 'fb-live-1',
                'status' => 'LIVE_STOPPED',
            ], 200),
            default => Http::response([], 404),
        };
    });

    $this->postJson("/api/projects/{$projectId}/live", [
        'format' => '720p',
    ])->assertCreated()
        ->assertJsonPath('data.metadata.destination_sessions.0.provider', 'twitch')
        ->assertJsonPath('data.metadata.destination_sessions.1.provider', 'meta')
        ->assertJsonPath('data.metadata.egress.outputs.0.full_rtmp_url', 'rtmp://sfo.contribute.live-video.net/app/live_user_123')
        ->assertJsonPath('data.metadata.egress.outputs.1.full_rtmp_url', 'rtmps://rtmp.facebook.com:443/rtmp/fb-stream-key?ds=1&a=token');

    $this->deleteJson("/api/projects/{$projectId}/live")
        ->assertOk();

    $liveStream = LiveStream::query()->latest()->first();
    $finalization = collect($liveStream->metadata['destination_finalization'] ?? []);

    expect($finalization->firstWhere('provider', 'twitch')['status'])->toBe('noop');
    expect($finalization->firstWhere('provider', 'meta')['status'])->toBe('completed');
});
