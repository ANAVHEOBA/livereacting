<?php

use App\Models\ConnectedAccount;
use App\Models\StreamingDestination;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('services.google_drive.client_id', 'drive-client');
    config()->set('services.google_drive.client_secret', 'drive-secret');
    config()->set('services.google_drive.redirect', 'http://localhost:8000/api/integrations/google-drive/callback');

    config()->set('services.youtube.client_id', 'youtube-client');
    config()->set('services.youtube.client_secret', 'youtube-secret');
    config()->set('services.youtube.redirect', 'http://localhost:8000/api/integrations/youtube/callback');

    config()->set('services.twitch.client_id', 'twitch-client');
    config()->set('services.twitch.client_secret', 'twitch-secret');
    config()->set('services.twitch.redirect', 'http://localhost:8000/api/integrations/twitch/callback');

    config()->set('services.meta.app_id', 'meta-app-id');
    config()->set('services.meta.app_secret', 'meta-app-secret');
    config()->set('services.meta.redirect', 'http://localhost:8000/api/integrations/meta/callback');
    config()->set('services.meta.graph_version', 'v22.0');

    config()->set('services.slack.client_id', 'slack-client');
    config()->set('services.slack.client_secret', 'slack-secret');
    config()->set('services.slack.redirect', 'http://localhost:8000/api/integrations/slack/callback');

    config()->set('services.dropbox.app_key', 'dropbox-key');
    config()->set('services.dropbox.app_secret', 'dropbox-secret');
    config()->set('services.dropbox.redirect', 'http://localhost:8000/api/integrations/dropbox/callback');
});

test('a user can connect google drive list assets and import a remote asset', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $authorizeResponse = $this->getJson('/api/integrations/google-drive/authorize');
    $state = $authorizeResponse->json('data.state');

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            $url === 'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'drive-access-token',
                'refresh_token' => 'drive-refresh-token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
                'token_type' => 'Bearer',
            ], 200),
            $url === 'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-1',
                'name' => 'Drive User',
                'email' => 'drive@example.com',
                'picture' => 'https://example.com/drive-user.png',
            ], 200),
            str_starts_with($url, 'https://www.googleapis.com/drive/v3/files/asset-1') => Http::response([
                'id' => 'asset-1',
                'name' => 'promo.mp4',
                'mimeType' => 'video/mp4',
                'size' => '10485760',
                'webContentLink' => 'https://drive.google.com/uc?id=asset-1',
                'thumbnailLink' => 'https://example.com/thumb.png',
                'videoMediaMetadata' => [
                    'durationMillis' => '45000',
                    'width' => 1920,
                    'height' => 1080,
                ],
                'fileExtension' => 'mp4',
            ], 200),
            str_starts_with($url, 'https://www.googleapis.com/drive/v3/files') => Http::response([
                'files' => [[
                    'id' => 'asset-1',
                    'name' => 'promo.mp4',
                    'mimeType' => 'video/mp4',
                    'size' => '10485760',
                    'webContentLink' => 'https://drive.google.com/uc?id=asset-1',
                    'thumbnailLink' => 'https://example.com/thumb.png',
                    'videoMediaMetadata' => [
                        'durationMillis' => '45000',
                        'width' => 1920,
                        'height' => 1080,
                    ],
                    'fileExtension' => 'mp4',
                ]],
            ], 200),
            default => Http::response([], 404),
        };
    });

    $callbackResponse = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/integrations/google-drive/callback?code=drive-code&state={$state}");

    $callbackResponse->assertOk()
        ->assertJsonPath('data.provider', 'google_drive')
        ->assertJsonPath('data.email', 'drive@example.com');

    $accountId = $callbackResponse->json('data.id');

    $this->getJson("/api/integrations/google-drive/assets?connected_account_id={$accountId}")
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.assets.0.id', 'asset-1');

    $this->postJson('/api/integrations/google-drive/imports', [
        'connected_account_id' => $accountId,
        'asset_id' => 'asset-1',
    ])->assertCreated()
        ->assertJsonPath('data.source', 'google_drive')
        ->assertJsonPath('data.file.source', 'google_drive')
        ->assertJsonPath('data.file.status', 'ready')
        ->assertJsonPath('data.file.metadata.connected_account_id', $accountId)
        ->assertJsonPath('data.file.metadata.provider_asset_id', 'asset-1');
});

test('a user can connect youtube twitch and meta destinations from provider accounts', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $youtubeState = $this->getJson('/api/integrations/youtube/authorize')->json('data.state');
    $twitchState = $this->getJson('/api/integrations/twitch/authorize')->json('data.state');
    $metaState = $this->getJson('/api/integrations/meta/authorize')->json('data.state');

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            $url === 'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'youtube-access-token',
                'refresh_token' => 'youtube-refresh-token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
            ], 200),
            $url === 'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'youtube-user-1',
                'name' => 'YouTube User',
                'email' => 'youtube@example.com',
                'picture' => 'https://example.com/youtube.png',
            ], 200),
            str_starts_with($url, 'https://www.googleapis.com/youtube/v3/channels') => Http::response([
                'items' => [[
                    'id' => 'UC123',
                    'snippet' => [
                        'title' => 'Studio Channel',
                        'thumbnails' => [
                            'default' => ['url' => 'https://example.com/youtube-channel.png'],
                        ],
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => ['uploads' => 'UU123'],
                    ],
                ]],
            ], 200),
            $url === 'https://id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'twitch-access-token',
                'refresh_token' => 'twitch-refresh-token',
                'expires_in' => 3600,
                'scope' => ['user:read:email'],
                'token_type' => 'bearer',
            ], 200),
            $url === 'https://api.twitch.tv/helix/users' => Http::response([
                'data' => [[
                    'id' => 'tw-1',
                    'login' => 'studiochannel',
                    'display_name' => 'Studio Channel',
                    'email' => 'twitch@example.com',
                    'profile_image_url' => 'https://example.com/twitch.png',
                ]],
            ], 200),
            str_starts_with($url, 'https://graph.facebook.com/v22.0/oauth/access_token') => Http::response([
                'access_token' => 'meta-access-token',
                'token_type' => 'bearer',
                'expires_in' => 7200,
            ], 200),
            str_starts_with($url, 'https://graph.facebook.com/v22.0/me/accounts') => Http::response([
                'data' => [[
                    'id' => 'page-1',
                    'name' => 'Main Page',
                    'category' => 'Media',
                    'link' => 'https://facebook.com/main-page',
                    'picture' => ['data' => ['url' => 'https://example.com/page.png']],
                    'access_token' => 'page-access-token',
                ]],
            ], 200),
            str_starts_with($url, 'https://graph.facebook.com/v22.0/me') => Http::response([
                'id' => 'meta-user-1',
                'name' => 'Meta User',
                'email' => 'meta@example.com',
                'picture' => ['data' => ['url' => 'https://example.com/meta-user.png']],
            ], 200),
            default => Http::response([], 404),
        };
    });

    $youtubeAccount = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/integrations/youtube/callback?code=youtube-code&state={$youtubeState}")
        ->assertOk()
        ->json('data.id');

    $twitchAccount = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/integrations/twitch/callback?code=twitch-code&state={$twitchState}")
        ->assertOk()
        ->json('data.id');

    $metaAccount = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/integrations/meta/callback?code=meta-code&state={$metaState}")
        ->assertOk()
        ->json('data.id');

    $this->getJson("/api/integrations/meta/destinations?connected_account_id={$metaAccount}")
        ->assertOk()
        ->assertJsonPath('data.destinations.0.platform_id', 'page-1');

    $this->postJson('/api/integrations/youtube/destinations', [
        'connected_account_id' => $youtubeAccount,
    ])->assertCreated()
        ->assertJsonPath('data.type', 'youtube')
        ->assertJsonPath('data.platform_id', 'UC123')
        ->assertJsonPath('data.connected_account_id', $youtubeAccount);

    $this->postJson('/api/integrations/twitch/destinations', [
        'connected_account_id' => $twitchAccount,
    ])->assertCreated()
        ->assertJsonPath('data.type', 'twitch')
        ->assertJsonPath('data.platform_id', 'tw-1')
        ->assertJsonPath('data.connected_account_id', $twitchAccount);

    $this->postJson('/api/integrations/meta/destinations', [
        'connected_account_id' => $metaAccount,
        'resource_id' => 'page-1',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'facebook')
        ->assertJsonPath('data.platform_id', 'page-1')
        ->assertJsonPath('data.connected_account_id', $metaAccount)
        ->assertJsonPath('data.is_valid', true);
});

test('a user can connect slack and send a test notification', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $state = $this->getJson('/api/integrations/slack/authorize')->json('data.state');

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            $url === 'https://slack.com/api/oauth.v2.access' => Http::response([
                'ok' => true,
                'access_token' => 'xoxb-test-token',
                'refresh_token' => 'slack-refresh-token',
                'expires_in' => 3600,
                'scope' => 'chat:write',
                'team' => [
                    'id' => 'T123',
                    'name' => 'Live Studio',
                ],
                'authed_user' => [
                    'id' => 'U123',
                ],
            ], 200),
            $url === 'https://slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'team' => 'Live Studio',
                'team_id' => 'T123',
                'url' => 'https://livestudio.slack.com/',
                'user' => 'studio-bot',
                'user_id' => 'U123',
                'bot_id' => 'B123',
            ], 200),
            $url === 'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => 'C123',
                'ts' => '12345.6789',
            ], 200),
            default => Http::response([], 404),
        };
    });

    $accountId = $this->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/integrations/slack/callback?code=slack-code&state={$state}")
        ->assertOk()
        ->assertJsonPath('data.provider', 'slack')
        ->json('data.id');

    $this->postJson('/api/integrations/slack/notify-test', [
        'connected_account_id' => $accountId,
        'channel' => 'C123',
        'text' => 'Studio heartbeat is green.',
    ])->assertOk()
        ->assertJsonPath('data.provider', 'slack')
        ->assertJsonPath('data.channel', 'C123')
        ->assertJsonPath('data.workspace', 'Live Studio');
});

test('connected accounts and destinations can be validated against provider apis', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $driveAccount = ConnectedAccount::create([
        'user_id' => $user->id,
        'provider' => 'google_drive',
        'external_id' => 'drive-user-1',
        'name' => 'Drive User',
        'email' => 'drive@example.com',
        'access_token' => 'drive-access-token',
        'refresh_token' => 'drive-refresh-token',
        'scopes' => ['https://www.googleapis.com/auth/drive.readonly'],
    ]);

    $twitchDestination = StreamingDestination::create([
        'user_id' => $user->id,
        'type' => 'twitch',
        'name' => 'Studio Twitch',
        'platform_id' => 'tw-1',
        'access_token' => 'twitch-access-token',
        'refresh_token' => 'twitch-refresh-token',
        'is_valid' => true,
    ]);

    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            $url === 'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'drive-user-1',
                'name' => 'Drive User',
                'email' => 'drive@example.com',
            ], 200),
            str_starts_with($url, 'https://www.googleapis.com/drive/v3/files') => Http::response([
                'files' => [[
                    'id' => 'asset-1',
                    'name' => 'promo.mp4',
                    'mimeType' => 'video/mp4',
                ]],
            ], 200),
            $url === 'https://api.twitch.tv/helix/streams/key?broadcaster_id=tw-1' => Http::response([
                'data' => [[
                    'stream_key' => 'live_user_123',
                ]],
            ], 200),
            default => Http::response([], 404),
        };
    });

    $this->getJson("/api/integrations/google-drive/validate?connected_account_id={$driveAccount->id}")
        ->assertOk()
        ->assertJsonPath('data.provider', 'google_drive')
        ->assertJsonPath('data.reachable', true)
        ->assertJsonPath('data.sample_assets', 1);

    $this->postJson("/api/destinations/{$twitchDestination->id}/probe")
        ->assertOk()
        ->assertJsonPath('data.provider', 'twitch')
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.has_stream_key', true);
});
