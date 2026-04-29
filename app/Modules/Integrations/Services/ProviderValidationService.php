<?php

namespace App\Modules\Integrations\Services;

use App\Models\ConnectedAccount;
use App\Models\StreamingDestination;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class ProviderValidationService
{
    public function validateConnectedAccount(ConnectedAccount $account): array
    {
        try {
            $result = match ($account->provider) {
                'google_drive' => $this->validateGoogleDriveAccount($account),
                'youtube' => $this->validateYoutubeAccount($account),
                'twitch' => $this->validateTwitchAccount($account),
                'meta' => $this->validateMetaAccount($account),
                'slack' => $this->validateSlackAccount($account),
                'dropbox' => $this->validateDropboxAccount($account),
                default => throw new \RuntimeException("Unsupported provider [{$account->provider}]"),
            };

            $payload = array_merge([
                'provider' => $account->provider,
                'connected_account_id' => $account->id,
                'reachable' => true,
                'is_expired' => $account->isExpired(),
                'checked_at' => now()->toIso8601String(),
                'scopes' => $account->scopes ?? [],
            ], $result);
        } catch (\Throwable $e) {
            $payload = [
                'provider' => $account->provider,
                'connected_account_id' => $account->id,
                'reachable' => false,
                'is_expired' => $account->isExpired(),
                'checked_at' => now()->toIso8601String(),
                'scopes' => $account->scopes ?? [],
                'error' => $e->getMessage(),
            ];
        }

        $account->update([
            'metadata' => array_merge($account->metadata ?? [], [
                'last_validation' => $payload,
            ]),
        ]);

        return $payload;
    }

    public function validateDestination(StreamingDestination $destination): array
    {
        try {
            $payload = match ($destination->type) {
                'rtmp' => $this->validateRtmpDestination($destination),
                'youtube' => $this->validateYoutubeDestination($destination),
                'twitch' => $this->validateTwitchDestination($destination),
                'facebook' => $this->validateMetaDestination($destination),
                default => throw new \RuntimeException("Unsupported destination type [{$destination->type}]"),
            };
        } catch (\Throwable $e) {
            $payload = [
                'provider' => $destination->type,
                'destination_id' => $destination->id,
                'reachable' => false,
                'valid' => false,
                'checked_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ];
        }

        $destination->update([
            'is_valid' => (bool) ($payload['valid'] ?? false),
            'metadata' => array_merge($destination->metadata ?? [], [
                'last_validation' => $payload,
            ]),
        ]);

        return $payload;
    }

    protected function validateGoogleDriveAccount(ConnectedAccount $account): array
    {
        $identity = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()
            ->json();

        $files = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/drive/v3/files', [
                'pageSize' => 1,
                'fields' => 'files(id,name,mimeType)',
            ])
            ->throw()
            ->json();

        return [
            'summary' => 'Google Drive account reachable',
            'account_name' => $identity['name'] ?? $account->name,
            'account_email' => $identity['email'] ?? $account->email,
            'sample_assets' => count($files['files'] ?? []),
            'capabilities' => ['assets' => true],
        ];
    }

    protected function validateYoutubeAccount(ConnectedAccount $account): array
    {
        $channels = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet,contentDetails,status',
                'mine' => 'true',
            ])
            ->throw()
            ->json();

        $channel = Arr::first($channels['items'] ?? []);

        if (! $channel) {
            throw new \RuntimeException('YouTube channel was not returned for the account');
        }

        $broadcasts = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/liveBroadcasts', [
                'part' => 'id,status',
                'broadcastStatus' => 'all',
                'mine' => 'true',
                'maxResults' => 1,
            ])
            ->throw()
            ->json();

        return [
            'summary' => 'YouTube account reachable',
            'channel_id' => $channel['id'],
            'channel_title' => Arr::get($channel, 'snippet.title'),
            'sample_broadcasts' => count($broadcasts['items'] ?? []),
            'capabilities' => [
                'assets' => true,
                'destinations' => true,
                'native_live' => true,
            ],
        ];
    }

    protected function validateTwitchAccount(ConnectedAccount $account): array
    {
        $headers = ['Client-ID' => (string) config('services.twitch.client_id')];

        $user = Http::withToken($account->access_token)
            ->withHeaders($headers)
            ->acceptJson()
            ->get('https://api.twitch.tv/helix/users')
            ->throw()
            ->json();

        $broadcaster = Arr::first($user['data'] ?? []);

        if (! $broadcaster) {
            throw new \RuntimeException('Twitch broadcaster profile was not returned');
        }

        $streamKey = Http::withToken($account->access_token)
            ->withHeaders($headers)
            ->acceptJson()
            ->get('https://api.twitch.tv/helix/streams/key', [
                'broadcaster_id' => $broadcaster['id'],
            ])
            ->throw()
            ->json();

        return [
            'summary' => 'Twitch account reachable',
            'channel_id' => $broadcaster['id'],
            'channel_title' => $broadcaster['display_name'] ?? $broadcaster['login'],
            'has_stream_key' => filled(Arr::get($streamKey, 'data.0.stream_key')),
            'capabilities' => [
                'destinations' => true,
                'native_live' => false,
            ],
        ];
    }

    protected function validateMetaAccount(ConnectedAccount $account): array
    {
        $graphVersion = config('services.meta.graph_version', 'v22.0');

        $profile = Http::withToken($account->access_token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$graphVersion}/me", [
                'fields' => 'id,name',
            ])
            ->throw()
            ->json();

        $pages = Http::withToken($account->access_token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$graphVersion}/me/accounts", [
                'fields' => 'id,name,category,access_token',
            ])
            ->throw()
            ->json();

        return [
            'summary' => 'Meta account reachable',
            'account_name' => $profile['name'] ?? $account->name,
            'page_count' => count($pages['data'] ?? []),
            'capabilities' => [
                'destinations' => true,
                'native_live' => true,
            ],
        ];
    }

    protected function validateSlackAccount(ConnectedAccount $account): array
    {
        $auth = Http::withToken($account->access_token)
            ->acceptJson()
            ->post('https://slack.com/api/auth.test')
            ->throw()
            ->json();

        if (! ($auth['ok'] ?? false)) {
            throw new \RuntimeException('Slack auth.test failed: '.($auth['error'] ?? 'unknown_error'));
        }

        return [
            'summary' => 'Slack workspace reachable',
            'workspace_name' => $auth['team'] ?? $account->name,
            'workspace_url' => $auth['url'] ?? null,
            'capabilities' => [
                'notifications' => true,
            ],
        ];
    }

    protected function validateDropboxAccount(ConnectedAccount $account): array
    {
        $profile = Http::withToken($account->access_token)
            ->acceptJson()
            ->post('https://api.dropboxapi.com/2/users/get_current_account')
            ->throw()
            ->json();

        $listing = Http::withToken($account->access_token)
            ->acceptJson()
            ->post('https://api.dropboxapi.com/2/files/list_folder', [
                'path' => '',
                'limit' => 1,
                'recursive' => false,
                'include_non_downloadable_files' => false,
            ])
            ->throw()
            ->json();

        return [
            'summary' => 'Dropbox account reachable',
            'account_name' => Arr::get($profile, 'name.display_name', $account->name),
            'account_email' => $profile['email'] ?? $account->email,
            'sample_assets' => count($listing['entries'] ?? []),
            'capabilities' => [
                'assets' => true,
            ],
        ];
    }

    protected function validateRtmpDestination(StreamingDestination $destination): array
    {
        $parts = parse_url((string) $destination->rtmp_url);

        return [
            'provider' => 'rtmp',
            'destination_id' => $destination->id,
            'reachable' => true,
            'valid' => filled($parts['host'] ?? null) && filled($destination->stream_key),
            'checked_at' => now()->toIso8601String(),
            'host' => $parts['host'] ?? null,
            'scheme' => $parts['scheme'] ?? null,
        ];
    }

    protected function validateYoutubeDestination(StreamingDestination $destination): array
    {
        $channels = Http::withToken($destination->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ])
            ->throw()
            ->json();

        $channel = Arr::first($channels['items'] ?? []);

        if (! $channel) {
            throw new \RuntimeException('YouTube destination channel not found');
        }

        $matches = ($channel['id'] ?? null) === $destination->platform_id;

        return [
            'provider' => 'youtube',
            'destination_id' => $destination->id,
            'reachable' => true,
            'valid' => $matches,
            'checked_at' => now()->toIso8601String(),
            'channel_id' => $channel['id'] ?? null,
            'channel_title' => Arr::get($channel, 'snippet.title'),
            'platform_match' => $matches,
        ];
    }

    protected function validateTwitchDestination(StreamingDestination $destination): array
    {
        $streamKey = Http::withToken($destination->access_token)
            ->withHeaders(['Client-ID' => (string) config('services.twitch.client_id')])
            ->acceptJson()
            ->get('https://api.twitch.tv/helix/streams/key', [
                'broadcaster_id' => $destination->platform_id,
            ])
            ->throw()
            ->json();

        $key = Arr::get($streamKey, 'data.0.stream_key');

        return [
            'provider' => 'twitch',
            'destination_id' => $destination->id,
            'reachable' => true,
            'valid' => filled($key),
            'checked_at' => now()->toIso8601String(),
            'has_stream_key' => filled($key),
        ];
    }

    protected function validateMetaDestination(StreamingDestination $destination): array
    {
        $graphVersion = config('services.meta.graph_version', 'v22.0');

        $page = Http::acceptJson()
            ->withToken($destination->access_token)
            ->get("https://graph.facebook.com/{$graphVersion}/{$destination->platform_id}", [
                'fields' => 'id,name,category',
            ])
            ->throw()
            ->json();

        return [
            'provider' => 'facebook',
            'destination_id' => $destination->id,
            'reachable' => true,
            'valid' => ($page['id'] ?? null) === $destination->platform_id,
            'checked_at' => now()->toIso8601String(),
            'page_id' => $page['id'] ?? null,
            'page_name' => $page['name'] ?? null,
            'platform_match' => ($page['id'] ?? null) === $destination->platform_id,
        ];
    }
}
