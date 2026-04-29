<?php

namespace App\Modules\Integrations\Services;

use App\Models\ConnectedAccount;
use App\Models\StreamingDestination;
use App\Models\User;
use App\Modules\Destinations\Services\DestinationService;
use App\Modules\Videos\Services\FileImportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IntegrationService
{
    public function __construct(
        protected DestinationService $destinationService,
        protected FileImportService $fileImportService
    ) {}

    public function getProviderCatalog(): array
    {
        return array_map(function (array $definition): array {
            return [
                'provider' => $definition['provider'],
                'route_key' => str_replace('_', '-', $definition['provider']),
                'label' => $definition['label'],
                'configured' => $this->isConfigured($definition['provider']),
                'capabilities' => $definition['capabilities'],
                'scopes' => $definition['scopes'],
                'redirect_uri' => $this->redirectUriFor($definition['provider']),
            ];
        }, $this->providerDefinitions());
    }

    public function listConnectedAccounts(User $user, ?string $provider = null): Collection
    {
        $query = ConnectedAccount::query()
            ->where('user_id', $user->id)
            ->latest();

        if ($provider) {
            $query->where('provider', $this->normalizeProvider($provider));
        }

        return $query->get();
    }

    public function buildAuthorizationUrl(User $user, string $provider): array
    {
        $provider = $this->normalizeProvider($provider);
        $definition = $this->providerDefinition($provider);

        if (! $this->isConfigured($provider)) {
            throw new \RuntimeException("{$definition['label']} is not configured");
        }

        $state = Str::random(40);
        $redirectUri = $this->redirectUriFor($provider);

        Cache::put($this->stateCacheKey($state), [
            'user_id' => $user->id,
            'provider' => $provider,
        ], now()->addMinutes(10));

        $query = [
            'client_id' => $this->clientIdFor($provider),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $this->scopeParameter($definition['scopes'], $provider),
            'state' => $state,
        ];

        $query = match ($provider) {
            'google_drive', 'youtube' => array_merge($query, [
                'access_type' => 'offline',
                'include_granted_scopes' => 'true',
                'prompt' => 'consent',
            ]),
            'dropbox' => array_merge($query, [
                'token_access_type' => 'offline',
            ]),
            'twitch' => array_merge($query, [
                'force_verify' => 'true',
            ]),
            default => $query,
        };

        return [
            'provider' => $provider,
            'authorization_url' => $definition['authorize_url'].'?'.http_build_query(array_filter(
                $query,
                static fn ($value): bool => filled($value)
            )),
            'state' => $state,
            'scopes' => $definition['scopes'],
            'redirect_uri' => $redirectUri,
        ];
    }

    public function handleCallback(string $provider, array $payload): ConnectedAccount
    {
        $provider = $this->normalizeProvider($provider);
        $definition = $this->providerDefinition($provider);

        if (! empty($payload['error'])) {
            throw new \RuntimeException($payload['error_description'] ?? $payload['error']);
        }

        $state = $payload['state'] ?? null;
        $code = $payload['code'] ?? null;

        if (blank($state) || blank($code)) {
            throw new \RuntimeException("Missing {$definition['label']} callback parameters");
        }

        $statePayload = Cache::pull($this->stateCacheKey($state));

        if (! $statePayload || ($statePayload['provider'] ?? null) !== $provider) {
            throw new \RuntimeException('OAuth state is invalid or has expired');
        }

        $tokenPayload = $this->exchangeAuthorizationCode($provider, $code);
        $identity = $this->fetchIdentity($provider, $tokenPayload);

        return $this->upsertConnectedAccount(
            (int) $statePayload['user_id'],
            $provider,
            $identity,
            $tokenPayload
        );
    }

    public function listAssets(User $user, string $provider, int $connectedAccountId, array $filters = []): array
    {
        $provider = $this->normalizeProvider($provider);
        $this->assertCapability($provider, 'assets');

        $account = $this->connectedAccountFor($user, $connectedAccountId, $provider);
        $account = $this->ensureFreshAccountToken($account);
        $limit = (int) ($filters['limit'] ?? 25);

        return match ($provider) {
            'google_drive' => $this->listGoogleDriveAssets($account, $filters['search'] ?? null, $limit),
            'dropbox' => $this->listDropboxAssets($account, $limit),
            'youtube' => $this->listYoutubeAssets($account, $limit),
            default => [],
        };
    }

    public function listDestinationOptions(User $user, string $provider, int $connectedAccountId): array
    {
        $provider = $this->normalizeProvider($provider);
        $this->assertCapability($provider, 'destinations');

        $account = $this->connectedAccountFor($user, $connectedAccountId, $provider);
        $account = $this->ensureFreshAccountToken($account);

        return $this->destinationOptions($account);
    }

    public function createDestination(User $user, string $provider, array $data): StreamingDestination
    {
        $provider = $this->normalizeProvider($provider);
        $this->assertCapability($provider, 'destinations');

        $account = $this->connectedAccountFor($user, (int) $data['connected_account_id'], $provider);
        $account = $this->ensureFreshAccountToken($account);

        return match ($provider) {
            'youtube' => $this->createYoutubeDestination($user, $account, $data),
            'twitch' => $this->createTwitchDestination($user, $account, $data),
            'meta' => $this->createMetaDestination($user, $account, $data),
            default => throw new \RuntimeException("{$provider} does not support streaming destinations"),
        };
    }

    public function importAsset(User $user, string $provider, array $data)
    {
        $provider = $this->normalizeProvider($provider);
        $this->assertCapability($provider, 'assets');

        $account = $this->connectedAccountFor($user, (int) $data['connected_account_id'], $provider);
        $account = $this->ensureFreshAccountToken($account);
        $descriptor = $this->assetDescriptor($account, $data['asset_id']);

        return $this->fileImportService->startImport($user->id, [
            'source' => $provider,
            'source_url' => $descriptor['source_url'],
            'type' => $data['type'] ?? $descriptor['type'],
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $data['name'] ?? $descriptor['name'],
            'size_bytes' => $descriptor['size_bytes'],
            'duration_seconds' => $descriptor['duration_seconds'],
            'resolution' => $descriptor['resolution'],
            'format' => $descriptor['format'],
            'metadata' => array_merge($descriptor['metadata'], [
                'connected_account_id' => $account->id,
                'provider_asset_id' => $data['asset_id'],
            ]),
        ]);
    }

    public function sendSlackTest(User $user, array $data): array
    {
        $account = $this->connectedAccountFor($user, (int) $data['connected_account_id'], 'slack');
        $account = $this->ensureFreshAccountToken($account);

        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->post('https://slack.com/api/chat.postMessage', array_filter([
                'channel' => $data['channel'],
                'text' => $data['text'],
                'blocks' => $data['blocks'] ?? null,
            ], static fn ($value): bool => ! is_null($value)));

        $payload = $response->json();

        if (! ($payload['ok'] ?? false)) {
            throw new \RuntimeException('Slack notification failed: '.($payload['error'] ?? 'unknown_error'));
        }

        return [
            'provider' => 'slack',
            'channel' => $payload['channel'] ?? $data['channel'],
            'ts' => $payload['ts'] ?? null,
            'workspace' => Arr::get($account->metadata, 'team.name'),
        ];
    }

    public function disconnectAccount(User $user, string $provider, int $id): void
    {
        $account = $this->connectedAccountFor($user, $id, $this->normalizeProvider($provider));

        $account->destinations()->update([
            'connected_account_id' => null,
            'is_valid' => false,
        ]);

        $account->delete();
    }

    public function refreshConnectedAccount(ConnectedAccount $account): ConnectedAccount
    {
        return $this->ensureFreshAccountToken($account->fresh());
    }

    public function getConnectedAccount(User $user, string $provider, int $id): ConnectedAccount
    {
        return $this->connectedAccountFor($user, $id, $this->normalizeProvider($provider));
    }

    public function buildFrontendCallbackUrl(string $provider, bool $success, array $extra = []): string
    {
        $query = array_filter(array_merge([
            'integration' => $this->normalizeProvider($provider),
            'status' => $success ? 'connected' : 'error',
        ], $extra), static fn ($value): bool => filled($value));

        return rtrim(config('streaming.integrations.frontend_redirect'), '?').'?'.http_build_query($query);
    }

    protected function providerDefinitions(): array
    {
        return [
            'google_drive' => [
                'provider' => 'google_drive',
                'label' => 'Google Drive',
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'scopes' => [
                    'https://www.googleapis.com/auth/drive.readonly',
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/userinfo.profile',
                ],
                'capabilities' => ['assets'],
            ],
            'youtube' => [
                'provider' => 'youtube',
                'label' => 'YouTube',
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'scopes' => [
                    'https://www.googleapis.com/auth/youtube',
                    'https://www.googleapis.com/auth/userinfo.email',
                    'https://www.googleapis.com/auth/userinfo.profile',
                ],
                'capabilities' => ['assets', 'destinations'],
            ],
            'twitch' => [
                'provider' => 'twitch',
                'label' => 'Twitch',
                'authorize_url' => 'https://id.twitch.tv/oauth2/authorize',
                'token_url' => 'https://id.twitch.tv/oauth2/token',
                'scopes' => ['user:read:email', 'channel:read:stream_key'],
                'capabilities' => ['destinations'],
            ],
            'meta' => [
                'provider' => 'meta',
                'label' => 'Meta/Facebook',
                'authorize_url' => sprintf(
                    'https://www.facebook.com/%s/dialog/oauth',
                    config('services.meta.graph_version', 'v22.0')
                ),
                'token_url' => sprintf(
                    'https://graph.facebook.com/%s/oauth/access_token',
                    config('services.meta.graph_version', 'v22.0')
                ),
                'scopes' => ['public_profile', 'email', 'pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'pages_manage_metadata', 'publish_video'],
                'capabilities' => ['destinations'],
            ],
            'slack' => [
                'provider' => 'slack',
                'label' => 'Slack',
                'authorize_url' => 'https://slack.com/oauth/v2/authorize',
                'token_url' => 'https://slack.com/api/oauth.v2.access',
                'scopes' => ['chat:write'],
                'capabilities' => ['notifications'],
            ],
            'dropbox' => [
                'provider' => 'dropbox',
                'label' => 'Dropbox',
                'authorize_url' => 'https://www.dropbox.com/oauth2/authorize',
                'token_url' => 'https://api.dropboxapi.com/oauth2/token',
                'scopes' => ['account_info.read', 'files.metadata.read', 'files.content.read'],
                'capabilities' => ['assets'],
            ],
        ];
    }

    protected function providerDefinition(string $provider): array
    {
        $definitions = $this->providerDefinitions();

        if (! isset($definitions[$provider])) {
            throw new \RuntimeException("Unsupported provider [{$provider}]");
        }

        return $definitions[$provider];
    }

    protected function normalizeProvider(string $provider): string
    {
        $provider = str_replace(['-', ' '], '_', Str::lower($provider));

        return match ($provider) {
            'facebook' => 'meta',
            default => $provider,
        };
    }

    protected function isConfigured(string $provider): bool
    {
        return filled($this->clientIdFor($provider))
            && filled($this->clientSecretFor($provider))
            && filled($this->redirectUriFor($provider));
    }

    protected function clientIdFor(string $provider): ?string
    {
        return match ($provider) {
            'google_drive' => config('services.google_drive.client_id'),
            'youtube' => config('services.youtube.client_id'),
            'twitch' => config('services.twitch.client_id'),
            'meta' => config('services.meta.app_id'),
            'slack' => config('services.slack.client_id'),
            'dropbox' => config('services.dropbox.app_key'),
            default => null,
        };
    }

    protected function clientSecretFor(string $provider): ?string
    {
        return match ($provider) {
            'google_drive' => config('services.google_drive.client_secret'),
            'youtube' => config('services.youtube.client_secret'),
            'twitch' => config('services.twitch.client_secret'),
            'meta' => config('services.meta.app_secret'),
            'slack' => config('services.slack.client_secret'),
            'dropbox' => config('services.dropbox.app_secret'),
            default => null,
        };
    }

    protected function redirectUriFor(string $provider): ?string
    {
        return match ($provider) {
            'google_drive' => config('services.google_drive.redirect'),
            'youtube' => config('services.youtube.redirect'),
            'twitch' => config('services.twitch.redirect'),
            'meta' => config('services.meta.redirect'),
            'slack' => config('services.slack.redirect'),
            'dropbox' => config('services.dropbox.redirect'),
            default => null,
        };
    }

    protected function scopeParameter(array $scopes, string $provider): string
    {
        return $provider === 'slack'
            ? implode(',', $scopes)
            : implode(' ', $scopes);
    }

    protected function stateCacheKey(string $state): string
    {
        return 'integrations:oauth:state:'.$state;
    }

    protected function connectedAccountFor(User $user, int $id, string $provider): ConnectedAccount
    {
        $account = ConnectedAccount::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (! $account) {
            throw new \RuntimeException('Connected account not found');
        }

        return $account;
    }

    protected function assertCapability(string $provider, string $capability): void
    {
        $definition = $this->providerDefinition($provider);

        if (! in_array($capability, $definition['capabilities'], true)) {
            throw new \RuntimeException("{$definition['label']} does not support {$capability}");
        }
    }

    protected function exchangeAuthorizationCode(string $provider, string $code): array
    {
        $definition = $this->providerDefinition($provider);
        $redirectUri = $this->redirectUriFor($provider);
        $clientId = $this->clientIdFor($provider);
        $clientSecret = $this->clientSecretFor($provider);

        $payload = match ($provider) {
            'google_drive', 'youtube' => Http::asForm()
                ->acceptJson()
                ->post($definition['token_url'], [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                ])->throw()->json(),
            'twitch' => Http::asForm()
                ->acceptJson()
                ->post($definition['token_url'], [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                ])->throw()->json(),
            'meta' => Http::acceptJson()
                ->get($definition['token_url'], [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ])->throw()->json(),
            'slack' => $this->assertSlackOk(Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->acceptJson()
                ->post($definition['token_url'], [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                ])->json(), 'Slack OAuth exchange'),
            'dropbox' => Http::asForm()
                ->acceptJson()
                ->post($definition['token_url'], [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                ])->throw()->json(),
            default => throw new \RuntimeException("Unsupported provider [{$provider}]"),
        };

        $payload['scope'] = $this->normalizeScopes($payload['scope'] ?? $definition['scopes']);

        return $payload;
    }

    protected function fetchIdentity(string $provider, array $tokenPayload): array
    {
        return match ($provider) {
            'google_drive' => $this->fetchGoogleDriveIdentity($tokenPayload['access_token']),
            'youtube' => $this->fetchYoutubeIdentity($tokenPayload['access_token']),
            'twitch' => $this->fetchTwitchIdentity($tokenPayload['access_token']),
            'meta' => $this->fetchMetaIdentity($tokenPayload['access_token']),
            'slack' => $this->fetchSlackIdentity($tokenPayload),
            'dropbox' => $this->fetchDropboxIdentity($tokenPayload['access_token']),
            default => throw new \RuntimeException("Unsupported provider [{$provider}]"),
        };
    }

    protected function fetchGoogleDriveIdentity(string $accessToken): array
    {
        $user = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()
            ->json();

        return [
            'external_id' => $user['sub'] ?? null,
            'name' => $user['name'] ?? $user['email'] ?? 'Google Drive',
            'email' => $user['email'] ?? null,
            'metadata' => [
                'picture' => $user['picture'] ?? null,
                'locale' => $user['locale'] ?? null,
            ],
        ];
    }

    protected function fetchYoutubeIdentity(string $accessToken): array
    {
        $user = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()
            ->json();

        $channelResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet,contentDetails',
                'mine' => 'true',
            ])->throw()->json();

        $channel = Arr::first($channelResponse['items'] ?? []);

        return [
            'external_id' => $channel['id'] ?? ($user['sub'] ?? null),
            'name' => Arr::get($channel, 'snippet.title', $user['name'] ?? $user['email'] ?? 'YouTube'),
            'email' => $user['email'] ?? null,
            'metadata' => [
                'picture' => Arr::get($channel, 'snippet.thumbnails.default.url', $user['picture'] ?? null),
                'uploads_playlist_id' => Arr::get($channel, 'contentDetails.relatedPlaylists.uploads'),
                'channel_id' => $channel['id'] ?? null,
            ],
        ];
    }

    protected function fetchTwitchIdentity(string $accessToken): array
    {
        $userResponse = Http::withToken($accessToken)
            ->withHeaders(['Client-ID' => (string) config('services.twitch.client_id')])
            ->acceptJson()
            ->get('https://api.twitch.tv/helix/users')
            ->throw()
            ->json();

        $user = Arr::first($userResponse['data'] ?? []);

        if (! $user) {
            throw new \RuntimeException('Unable to resolve Twitch account');
        }

        return [
            'external_id' => $user['id'],
            'name' => $user['display_name'] ?? $user['login'],
            'email' => $user['email'] ?? null,
            'metadata' => [
                'login' => $user['login'] ?? null,
                'profile_image_url' => $user['profile_image_url'] ?? null,
            ],
        ];
    }

    protected function fetchMetaIdentity(string $accessToken): array
    {
        $graphVersion = config('services.meta.graph_version', 'v22.0');

        $user = Http::withToken($accessToken)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$graphVersion}/me", [
                'fields' => 'id,name,email,picture{url}',
            ])->throw()->json();

        return [
            'external_id' => $user['id'] ?? null,
            'name' => $user['name'] ?? 'Meta',
            'email' => $user['email'] ?? null,
            'metadata' => [
                'picture' => Arr::get($user, 'picture.data.url'),
                'graph_version' => $graphVersion,
            ],
        ];
    }

    protected function fetchSlackIdentity(array $tokenPayload): array
    {
        $auth = $this->assertSlackOk(Http::withToken($tokenPayload['access_token'])
            ->acceptJson()
            ->post('https://slack.com/api/auth.test')
            ->json(), 'Slack auth.test');

        return [
            'external_id' => $auth['team_id'] ?? Arr::get($tokenPayload, 'team.id'),
            'name' => $auth['team'] ?? Arr::get($tokenPayload, 'team.name') ?? 'Slack Workspace',
            'email' => null,
            'metadata' => [
                'team' => [
                    'id' => $auth['team_id'] ?? Arr::get($tokenPayload, 'team.id'),
                    'name' => $auth['team'] ?? Arr::get($tokenPayload, 'team.name'),
                    'url' => $auth['url'] ?? null,
                ],
                'bot_user_id' => $auth['bot_id'] ?? Arr::get($tokenPayload, 'bot_user_id'),
                'incoming_webhook' => Arr::get($tokenPayload, 'incoming_webhook'),
                'authed_user' => Arr::get($tokenPayload, 'authed_user'),
            ],
        ];
    }

    protected function fetchDropboxIdentity(string $accessToken): array
    {
        $account = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://api.dropboxapi.com/2/users/get_current_account')
            ->throw()
            ->json();

        return [
            'external_id' => $account['account_id'] ?? null,
            'name' => Arr::get($account, 'name.display_name', 'Dropbox'),
            'email' => $account['email'] ?? null,
            'metadata' => [
                'disabled' => $account['disabled'] ?? false,
                'profile_photo_url' => $account['profile_photo_url'] ?? null,
                'account_type' => Arr::get($account, 'account_type..tag'),
            ],
        ];
    }

    protected function upsertConnectedAccount(int $userId, string $provider, array $identity, array $tokenPayload): ConnectedAccount
    {
        $account = ConnectedAccount::query()->firstOrNew([
            'user_id' => $userId,
            'provider' => $provider,
            'external_id' => $identity['external_id'] ?? null,
        ]);

        $account->fill([
            'name' => $identity['name'] ?? null,
            'email' => $identity['email'] ?? null,
            'access_token' => $tokenPayload['access_token'] ?? $account->access_token,
            'refresh_token' => $tokenPayload['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($tokenPayload['expires_in'])
                ? now()->addSeconds((int) $tokenPayload['expires_in'])
                : $account->token_expires_at,
            'scopes' => $this->normalizeScopes($tokenPayload['scope'] ?? []),
            'metadata' => $identity['metadata'] ?? [],
        ]);
        $account->save();

        return $account->fresh();
    }

    protected function ensureFreshAccountToken(ConnectedAccount $account): ConnectedAccount
    {
        if (! $account->isExpired()) {
            return $account;
        }

        if (blank($account->refresh_token)) {
            throw new \RuntimeException("{$account->provider} access token has expired and cannot be refreshed");
        }

        $refreshed = match ($account->provider) {
            'google_drive', 'youtube' => Http::asForm()
                ->acceptJson()
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $this->clientIdFor($account->provider),
                    'client_secret' => $this->clientSecretFor($account->provider),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ])->throw()->json(),
            'twitch' => Http::asForm()
                ->acceptJson()
                ->post('https://id.twitch.tv/oauth2/token', [
                    'client_id' => $this->clientIdFor($account->provider),
                    'client_secret' => $this->clientSecretFor($account->provider),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ])->throw()->json(),
            'slack' => $this->assertSlackOk(Http::asForm()
                ->withBasicAuth((string) $this->clientIdFor($account->provider), (string) $this->clientSecretFor($account->provider))
                ->acceptJson()
                ->post('https://slack.com/api/oauth.v2.access', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ])->json(), 'Slack token refresh'),
            'dropbox' => Http::asForm()
                ->acceptJson()
                ->post('https://api.dropboxapi.com/oauth2/token', [
                    'client_id' => $this->clientIdFor($account->provider),
                    'client_secret' => $this->clientSecretFor($account->provider),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account->refresh_token,
                ])->throw()->json(),
            default => throw new \RuntimeException("{$account->provider} refresh is not supported"),
        };

        $account->update([
            'access_token' => $refreshed['access_token'] ?? $account->access_token,
            'refresh_token' => $refreshed['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => isset($refreshed['expires_in'])
                ? now()->addSeconds((int) $refreshed['expires_in'])
                : $account->token_expires_at,
            'scopes' => $this->normalizeScopes($refreshed['scope'] ?? $account->scopes ?? []),
        ]);

        $account->destinations()->update([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'token_expires_at' => $account->token_expires_at,
            'is_valid' => true,
        ]);

        return $account->fresh();
    }

    protected function destinationOptions(ConnectedAccount $account, bool $includeSecrets = false): array
    {
        return match ($account->provider) {
            'youtube' => [$this->youtubeDestinationOption($account)],
            'twitch' => [$this->twitchDestinationOption($account)],
            'meta' => $this->metaDestinationOptions($account, $includeSecrets),
            default => [],
        };
    }

    protected function createYoutubeDestination(User $user, ConnectedAccount $account, array $data): StreamingDestination
    {
        $channel = $this->youtubeDestinationOption($account);

        return $this->destinationService->createDestination($user->id, [
            'connected_account_id' => $account->id,
            'type' => 'youtube',
            'name' => $data['name'] ?? $channel['name'],
            'platform_id' => $channel['platform_id'],
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'token_expires_at' => $account->token_expires_at,
            'metadata' => $channel['metadata'],
        ]);
    }

    protected function createTwitchDestination(User $user, ConnectedAccount $account, array $data): StreamingDestination
    {
        $channel = $this->twitchDestinationOption($account);

        return $this->destinationService->createDestination($user->id, [
            'connected_account_id' => $account->id,
            'type' => 'twitch',
            'name' => $data['name'] ?? $channel['name'],
            'platform_id' => $channel['platform_id'],
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'token_expires_at' => $account->token_expires_at,
            'metadata' => $channel['metadata'],
        ]);
    }

    protected function createMetaDestination(User $user, ConnectedAccount $account, array $data): StreamingDestination
    {
        $pages = $this->metaDestinationOptions($account, true);
        $target = collect($pages)->firstWhere('resource_id', $data['resource_id'] ?? null) ?? Arr::first($pages);

        if (! $target) {
            throw new \RuntimeException('No Facebook pages are available for this connection');
        }

        return $this->destinationService->createDestination($user->id, [
            'connected_account_id' => $account->id,
            'type' => 'facebook',
            'name' => $data['name'] ?? $target['name'],
            'platform_id' => $target['platform_id'],
            'access_token' => $target['access_token'] ?? $account->access_token,
            'token_expires_at' => $account->token_expires_at,
            'metadata' => $target['metadata'],
        ]);
    }

    protected function youtubeDestinationOption(ConnectedAccount $account): array
    {
        return [
            'resource_id' => $account->external_id,
            'platform_id' => Arr::get($account->metadata, 'channel_id', $account->external_id),
            'name' => $account->name,
            'provider' => 'youtube',
            'metadata' => [
                'thumbnail_url' => Arr::get($account->metadata, 'picture'),
                'uploads_playlist_id' => Arr::get($account->metadata, 'uploads_playlist_id'),
            ],
        ];
    }

    protected function twitchDestinationOption(ConnectedAccount $account): array
    {
        return [
            'resource_id' => $account->external_id,
            'platform_id' => $account->external_id,
            'name' => $account->name,
            'provider' => 'twitch',
            'metadata' => [
                'login' => Arr::get($account->metadata, 'login'),
                'profile_image_url' => Arr::get($account->metadata, 'profile_image_url'),
            ],
        ];
    }

    protected function metaDestinationOptions(ConnectedAccount $account, bool $includeSecrets = false): array
    {
        $graphVersion = config('services.meta.graph_version', 'v22.0');

        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->get("https://graph.facebook.com/{$graphVersion}/me/accounts", [
                'fields' => 'id,name,category,link,picture{url},access_token',
            ])->throw()->json();

        return collect($response['data'] ?? [])
            ->map(function (array $page) use ($includeSecrets): array {
                $option = [
                    'resource_id' => $page['id'],
                    'platform_id' => $page['id'],
                    'name' => $page['name'] ?? 'Facebook Page',
                    'provider' => 'meta',
                    'metadata' => [
                        'category' => $page['category'] ?? null,
                        'link' => $page['link'] ?? null,
                        'picture_url' => Arr::get($page, 'picture.data.url'),
                    ],
                ];

                if ($includeSecrets) {
                    $option['access_token'] = $page['access_token'] ?? null;
                }

                return $option;
            })
            ->values()
            ->all();
    }

    protected function listGoogleDriveAssets(ConnectedAccount $account, ?string $search, int $limit): array
    {
        $query = [
            'pageSize' => $limit,
            'orderBy' => 'modifiedTime desc',
            'fields' => 'files(id,name,mimeType,size,modifiedTime,thumbnailLink,webViewLink,webContentLink,videoMediaMetadata(durationMillis,height,width),imageMediaMetadata(height,width),fileExtension)',
            'q' => $search
                ? sprintf("trashed=false and name contains '%s'", addslashes($search))
                : 'trashed=false',
        ];

        $payload = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/drive/v3/files', $query)
            ->throw()
            ->json();

        return collect($payload['files'] ?? [])
            ->map(fn (array $file): array => $this->mapGoogleDriveAsset($file))
            ->values()
            ->all();
    }

    protected function mapGoogleDriveAsset(array $file): array
    {
        $type = $this->typeFromMime($file['mimeType'] ?? '', $file['name'] ?? '');

        return [
            'id' => $file['id'],
            'name' => $file['name'],
            'type' => $type,
            'source_url' => $file['webContentLink']
                ?? $file['webViewLink']
                ?? sprintf('https://drive.google.com/file/d/%s/view', $file['id']),
            'size_bytes' => isset($file['size']) ? (int) $file['size'] : null,
            'duration_seconds' => isset($file['videoMediaMetadata']['durationMillis'])
                ? (int) floor(((int) $file['videoMediaMetadata']['durationMillis']) / 1000)
                : null,
            'resolution' => $this->googleDriveResolution($file),
            'format' => $file['fileExtension'] ?? $this->extensionFromMime($file['mimeType'] ?? ''),
            'thumbnail_url' => $file['thumbnailLink'] ?? null,
            'metadata' => [
                'mime_type' => $file['mimeType'] ?? null,
                'modified_time' => $file['modifiedTime'] ?? null,
            ],
        ];
    }

    protected function listDropboxAssets(ConnectedAccount $account, int $limit): array
    {
        $payload = Http::withToken($account->access_token)
            ->acceptJson()
            ->post('https://api.dropboxapi.com/2/files/list_folder', [
                'path' => '',
                'limit' => $limit,
                'recursive' => false,
                'include_non_downloadable_files' => false,
            ])->throw()->json();

        return collect($payload['entries'] ?? [])
            ->filter(static fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file')
            ->map(fn (array $entry): array => [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'type' => $this->typeFromMime(null, $entry['name']),
                'source_url' => 'https://www.dropbox.com/home'.($entry['path_display'] ?? ''),
                'size_bytes' => isset($entry['size']) ? (int) $entry['size'] : null,
                'duration_seconds' => null,
                'resolution' => null,
                'format' => pathinfo($entry['name'], PATHINFO_EXTENSION) ?: null,
                'thumbnail_url' => null,
                'metadata' => [
                    'path_display' => $entry['path_display'] ?? null,
                    'client_modified' => $entry['client_modified'] ?? null,
                ],
            ])
            ->values()
            ->all();
    }

    protected function listYoutubeAssets(ConnectedAccount $account, int $limit): array
    {
        $playlistId = Arr::get($account->metadata, 'uploads_playlist_id');

        if (blank($playlistId)) {
            return [];
        }

        $playlistItems = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'part' => 'snippet,contentDetails',
                'playlistId' => $playlistId,
                'maxResults' => min($limit, 50),
            ])->throw()->json();

        $items = $playlistItems['items'] ?? [];
        $videoIds = collect($items)
            ->pluck('contentDetails.videoId')
            ->filter()
            ->values()
            ->all();

        if ($videoIds === []) {
            return [];
        }

        $videoDetails = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'contentDetails,snippet',
                'id' => implode(',', $videoIds),
            ])->throw()->json();

        $detailMap = collect($videoDetails['items'] ?? [])->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($detailMap): array {
                $videoId = Arr::get($item, 'contentDetails.videoId');
                $detail = $detailMap->get($videoId, []);

                return [
                    'id' => $videoId,
                    'name' => Arr::get($item, 'snippet.title', 'YouTube video'),
                    'type' => 'video',
                    'source_url' => sprintf('https://www.youtube.com/watch?v=%s', $videoId),
                    'size_bytes' => null,
                    'duration_seconds' => $this->secondsFromIsoDuration(Arr::get($detail, 'contentDetails.duration')),
                    'resolution' => Arr::get($detail, 'contentDetails.definition'),
                    'format' => 'youtube',
                    'thumbnail_url' => Arr::get($item, 'snippet.thumbnails.medium.url'),
                    'metadata' => [
                        'published_at' => Arr::get($item, 'snippet.publishedAt'),
                        'channel_title' => Arr::get($item, 'snippet.channelTitle'),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    protected function assetDescriptor(ConnectedAccount $account, string $assetId): array
    {
        return match ($account->provider) {
            'google_drive' => $this->googleDriveAssetDescriptor($account, $assetId),
            'dropbox' => $this->dropboxAssetDescriptor($account, $assetId),
            'youtube' => $this->youtubeAssetDescriptor($account, $assetId),
            default => throw new \RuntimeException("{$account->provider} does not support asset import"),
        };
    }

    protected function googleDriveAssetDescriptor(ConnectedAccount $account, string $assetId): array
    {
        $file = Http::withToken($account->access_token)
            ->acceptJson()
            ->get("https://www.googleapis.com/drive/v3/files/{$assetId}", [
                'fields' => 'id,name,mimeType,size,thumbnailLink,webViewLink,webContentLink,videoMediaMetadata(durationMillis,height,width),imageMediaMetadata(height,width),fileExtension',
            ])->throw()->json();

        return $this->mapGoogleDriveAsset($file);
    }

    protected function dropboxAssetDescriptor(ConnectedAccount $account, string $assetId): array
    {
        $file = Http::withToken($account->access_token)
            ->acceptJson()
            ->post('https://api.dropboxapi.com/2/files/get_metadata', [
                'path' => $assetId,
                'include_media_info' => true,
            ])->throw()->json();

        $pathDisplay = $file['path_display'] ?? '';
        $resolution = null;
        $dimensions = Arr::get($file, 'media_info.metadata.dimensions');

        if (is_array($dimensions) && isset($dimensions['width'], $dimensions['height'])) {
            $resolution = $dimensions['width'].'x'.$dimensions['height'];
        }

        return [
            'id' => $file['id'],
            'name' => $file['name'],
            'type' => $this->typeFromMime(null, $file['name']),
            'source_url' => 'https://www.dropbox.com/home'.$pathDisplay,
            'size_bytes' => isset($file['size']) ? (int) $file['size'] : null,
            'duration_seconds' => Arr::get($file, 'media_info.metadata.duration'),
            'resolution' => $resolution,
            'format' => pathinfo($file['name'], PATHINFO_EXTENSION) ?: null,
            'metadata' => [
                'path_display' => $pathDisplay,
                'client_modified' => $file['client_modified'] ?? null,
            ],
        ];
    }

    protected function youtubeAssetDescriptor(ConnectedAccount $account, string $assetId): array
    {
        $payload = Http::withToken($account->access_token)
            ->acceptJson()
            ->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'contentDetails,snippet',
                'id' => $assetId,
            ])->throw()->json();

        $video = Arr::first($payload['items'] ?? []);

        if (! $video) {
            throw new \RuntimeException('YouTube asset not found');
        }

        return [
            'id' => $video['id'],
            'name' => Arr::get($video, 'snippet.title', 'YouTube video'),
            'type' => 'video',
            'source_url' => sprintf('https://www.youtube.com/watch?v=%s', $video['id']),
            'size_bytes' => null,
            'duration_seconds' => $this->secondsFromIsoDuration(Arr::get($video, 'contentDetails.duration')),
            'resolution' => Arr::get($video, 'contentDetails.definition'),
            'format' => 'youtube',
            'metadata' => [
                'published_at' => Arr::get($video, 'snippet.publishedAt'),
                'channel_title' => Arr::get($video, 'snippet.channelTitle'),
            ],
        ];
    }

    protected function normalizeScopes(array|string|null $scope): array
    {
        if (is_array($scope)) {
            return array_values(array_filter($scope));
        }

        if (blank($scope)) {
            return [];
        }

        return array_values(array_filter(preg_split('/[\s,]+/', trim($scope))));
    }

    protected function assertSlackOk(array $payload, string $context): array
    {
        if (! ($payload['ok'] ?? false)) {
            throw new \RuntimeException($context.' failed: '.($payload['error'] ?? 'unknown_error'));
        }

        return $payload;
    }

    protected function typeFromMime(?string $mimeType, string $name): string
    {
        if ($mimeType) {
            if (str_starts_with($mimeType, 'audio/')) {
                return 'audio';
            }

            if (str_starts_with($mimeType, 'image/')) {
                return 'image';
            }
        }

        $extension = Str::lower((string) pathinfo($name, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp3', 'wav', 'aac', 'm4a' => 'audio',
            'png', 'jpg', 'jpeg', 'gif', 'webp' => 'image',
            default => 'video',
        };
    }

    protected function extensionFromMime(string $mimeType): ?string
    {
        return match ($mimeType) {
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => null,
        };
    }

    protected function googleDriveResolution(array $file): ?string
    {
        $video = $file['videoMediaMetadata'] ?? null;
        $image = $file['imageMediaMetadata'] ?? null;

        if (isset($video['width'], $video['height'])) {
            return $video['width'].'x'.$video['height'];
        }

        if (isset($image['width'], $image['height'])) {
            return $image['width'].'x'.$image['height'];
        }

        return null;
    }

    protected function secondsFromIsoDuration(?string $duration): ?int
    {
        if (blank($duration)) {
            return null;
        }

        $interval = new \DateInterval($duration);

        return ($interval->d * 86400)
            + ($interval->h * 3600)
            + ($interval->i * 60)
            + $interval->s;
    }
}
