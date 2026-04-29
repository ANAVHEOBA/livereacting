<?php

namespace App\Modules\Projects\Services;

use App\Models\LiveStream;
use App\Models\Project;
use App\Models\StreamingDestination;
use App\Modules\Integrations\Services\IntegrationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LiveDestinationProvisioningService
{
    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function provision(Project $project, LiveStream $liveStream, array $options = []): array
    {
        $destinations = $project->destinations()->with('connectedAccount')->get();
        $scheduledStart = Carbon::parse($options['scheduled_start_at'] ?? now()->addMinute()->toIso8601String());
        $scheduledEnd = isset($options['duration'])
            ? $scheduledStart->copy()->addSeconds((int) $options['duration'])
            : null;

        $sessions = [];
        $outputs = [];

        foreach ($destinations as $destination) {
            $destination = $this->hydrateRuntimeCredentials($destination);

            $session = match ($destination->type) {
                'youtube' => $this->provisionYoutubeDestination($project, $liveStream, $destination, $scheduledStart, $scheduledEnd),
                'facebook' => $this->provisionMetaDestination($project, $liveStream, $destination, $options),
                'twitch' => $this->provisionTwitchDestination($project, $liveStream, $destination),
                default => $this->provisionRtmpDestination($project, $liveStream, $destination),
            };

            $sessions[] = $session;
            $outputs[] = $session['output'];

            $this->storeDestinationRuntimeMetadata($destination, $session);
        }

        return [
            'sessions' => $sessions,
            'egress' => [
                'driver' => config('streaming.engine.driver'),
                'output_mode' => config('streaming.engine.output_mode'),
                'outputs' => $outputs,
            ],
        ];
    }

    public function finalize(LiveStream $liveStream): array
    {
        $project = $liveStream->project()->with('destinations.connectedAccount')->firstOrFail();
        $sessions = $liveStream->metadata['destination_sessions'] ?? [];
        $results = [];

        foreach ($sessions as $session) {
            $destination = $project->destinations->firstWhere('id', $session['destination_id'] ?? null);

            if (! $destination) {
                $results[] = [
                    'destination_id' => $session['destination_id'] ?? null,
                    'provider' => $session['provider'] ?? null,
                    'status' => 'skipped',
                    'message' => 'Destination no longer exists',
                ];

                continue;
            }

            $destination = $this->hydrateRuntimeCredentials($destination);

            try {
                $results[] = match ($session['provider']) {
                    'youtube' => $this->finalizeYoutubeDestination($destination, $session),
                    'meta' => $this->finalizeMetaDestination($destination, $session),
                    default => [
                        'destination_id' => $destination->id,
                        'provider' => $this->normalizeProvider($destination->type),
                        'status' => 'noop',
                    ],
                };
            } catch (\Throwable $e) {
                $results[] = [
                    'destination_id' => $destination->id,
                    'provider' => $session['provider'] ?? $this->normalizeProvider($destination->type),
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    protected function provisionRtmpDestination(Project $project, LiveStream $liveStream, StreamingDestination $destination): array
    {
        $fullRtmpUrl = $this->buildFullRtmpUrl($destination->rtmp_url, $destination->stream_key);

        return [
            'destination_id' => $destination->id,
            'provider' => 'rtmp',
            'name' => $destination->name,
            'status' => 'ready',
            'output' => [
                'destination_id' => $destination->id,
                'provider' => 'rtmp',
                'name' => $destination->name,
                'rtmp_url' => $destination->rtmp_url,
                'stream_key' => $destination->stream_key,
                'full_rtmp_url' => $fullRtmpUrl,
                'resolution' => $liveStream->format,
            ],
            'provisioned_at' => now()->toIso8601String(),
        ];
    }

    protected function provisionYoutubeDestination(
        Project $project,
        LiveStream $liveStream,
        StreamingDestination $destination,
        Carbon $scheduledStart,
        ?Carbon $scheduledEnd
    ): array {
        if (blank($destination->access_token)) {
            throw new \RuntimeException('YouTube destination is missing an access token');
        }

        [$resolution, $frameRate] = $this->youtubeEncodingProfile($liveStream->format);
        $title = Str::limit($project->name.' '.$scheduledStart->format('Y-m-d H:i'), 100, '');
        $description = Str::limit((string) $project->description, 5000, '');

        $streamPayload = Http::withToken($destination->access_token)
            ->acceptJson()
            ->post('https://www.googleapis.com/youtube/v3/liveStreams?part=id,snippet,cdn,contentDetails,status', [
                'snippet' => [
                    'title' => $title,
                    'description' => $description,
                ],
                'cdn' => [
                    'frameRate' => $frameRate,
                    'ingestionType' => 'rtmp',
                    'resolution' => $resolution,
                ],
                'contentDetails' => [
                    'isReusable' => false,
                ],
            ])
            ->throw()
            ->json();

        $broadcastPayload = Http::withToken($destination->access_token)
            ->acceptJson()
            ->post('https://www.googleapis.com/youtube/v3/liveBroadcasts?part=id,snippet,contentDetails,status', array_filter([
                'snippet' => array_filter([
                    'title' => $title,
                    'description' => $description,
                    'scheduledStartTime' => $scheduledStart->toIso8601String(),
                    'scheduledEndTime' => $scheduledEnd?->toIso8601String(),
                ], static fn ($value): bool => filled($value)),
                'status' => [
                    'privacyStatus' => config('streaming.providers.youtube.privacy_status', 'unlisted'),
                ],
                'contentDetails' => [
                    'enableAutoStart' => (bool) config('streaming.providers.youtube.auto_start', true),
                    'enableAutoStop' => (bool) config('streaming.providers.youtube.auto_stop', true),
                    'enableDvr' => true,
                    'recordFromStart' => true,
                ],
            ]))
            ->throw()
            ->json();

        $bindPayload = Http::withToken($destination->access_token)
            ->acceptJson()
            ->post(sprintf(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts/bind?id=%s&part=id,contentDetails,status&streamId=%s',
                $broadcastPayload['id'],
                $streamPayload['id']
            ))
            ->throw()
            ->json();

        $ingestionAddress = Arr::get($streamPayload, 'cdn.ingestionInfo.ingestionAddress');
        $streamName = Arr::get($streamPayload, 'cdn.ingestionInfo.streamName');

        return [
            'destination_id' => $destination->id,
            'provider' => 'youtube',
            'name' => $destination->name,
            'status' => 'provisioned',
            'broadcast_id' => $broadcastPayload['id'],
            'stream_id' => $streamPayload['id'],
            'youtube_status' => Arr::get($bindPayload, 'status.lifeCycleStatus'),
            'output' => [
                'destination_id' => $destination->id,
                'provider' => 'youtube',
                'name' => $destination->name,
                'rtmp_url' => $ingestionAddress,
                'stream_key' => $streamName,
                'full_rtmp_url' => $this->buildFullRtmpUrl($ingestionAddress, $streamName),
                'resolution' => $liveStream->format,
                'metadata' => [
                    'broadcast_id' => $broadcastPayload['id'],
                    'stream_id' => $streamPayload['id'],
                    'privacy_status' => config('streaming.providers.youtube.privacy_status', 'unlisted'),
                ],
            ],
            'provisioned_at' => now()->toIso8601String(),
        ];
    }

    protected function provisionMetaDestination(
        Project $project,
        LiveStream $liveStream,
        StreamingDestination $destination,
        array $options
    ): array {
        if (blank($destination->access_token)) {
            throw new \RuntimeException('Meta destination is missing a page access token');
        }

        $graphVersion = config('services.meta.graph_version', 'v22.0');
        $title = $project->name;

        $payload = Http::acceptJson()
            ->asForm()
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/live_videos',
                $graphVersion,
                $destination->platform_id
            ), array_filter([
                'access_token' => $destination->access_token,
                'title' => $title,
                'description' => $project->description,
                'status' => config('streaming.providers.meta.status', 'LIVE_NOW'),
                'broadcast_type' => 'RTMP',
                'live_video_preset' => config('streaming.providers.meta.live_video_preset'),
            ], static fn ($value): bool => filled($value)))
            ->throw()
            ->json();

        $output = $this->parseMetaStreamUrl($payload['secure_stream_url'] ?? $payload['stream_url'] ?? null);

        return [
            'destination_id' => $destination->id,
            'provider' => 'meta',
            'name' => $destination->name,
            'status' => 'provisioned',
            'live_video_id' => $payload['id'] ?? null,
            'permalink_url' => $payload['permalink_url'] ?? null,
            'embed_html' => $payload['embed_html'] ?? null,
            'output' => array_merge($output, [
                'destination_id' => $destination->id,
                'provider' => 'meta',
                'name' => $destination->name,
                'resolution' => $liveStream->format,
                'metadata' => [
                    'live_video_id' => $payload['id'] ?? null,
                    'permalink_url' => $payload['permalink_url'] ?? null,
                ],
            ]),
            'provisioned_at' => now()->toIso8601String(),
        ];
    }

    protected function provisionTwitchDestination(Project $project, LiveStream $liveStream, StreamingDestination $destination): array
    {
        if (blank($destination->access_token)) {
            throw new \RuntimeException('Twitch destination is missing an access token');
        }

        $streamKeyPayload = Http::withToken($destination->access_token)
            ->withHeaders(['Client-Id' => (string) config('services.twitch.client_id')])
            ->acceptJson()
            ->get('https://api.twitch.tv/helix/streams/key', [
                'broadcaster_id' => $destination->platform_id,
            ])
            ->throw()
            ->json();

        $streamKey = Arr::get($streamKeyPayload, 'data.0.stream_key');

        if (blank($streamKey)) {
            throw new \RuntimeException('Twitch did not return a stream key');
        }

        $ingests = Http::acceptJson()
            ->get('https://ingest.twitch.tv/ingests')
            ->throw()
            ->json();

        $ingest = collect($ingests['ingests'] ?? [])
            ->firstWhere('default', true)
            ?? Arr::first($ingests['ingests'] ?? []);

        $template = Arr::get($ingest, 'url_template', config('streaming.providers.twitch.fallback_ingest_url', 'rtmp://live.twitch.tv/app/{stream_key}'));
        $fullRtmpUrl = str_replace('{stream_key}', $streamKey, $template);
        $rtmpUrl = rtrim(str_replace('{stream_key}', '', $template), '/');

        $destination->update([
            'rtmp_url' => $rtmpUrl,
            'stream_key' => $streamKey,
            'metadata' => array_merge($destination->metadata ?? [], [
                'ingest_server' => [
                    'name' => $ingest['name'] ?? 'Twitch Ingest',
                    'url_template' => $template,
                ],
            ]),
        ]);

        return [
            'destination_id' => $destination->id,
            'provider' => 'twitch',
            'name' => $destination->name,
            'status' => 'ready',
            'output' => [
                'destination_id' => $destination->id,
                'provider' => 'twitch',
                'name' => $destination->name,
                'rtmp_url' => $rtmpUrl,
                'stream_key' => $streamKey,
                'full_rtmp_url' => $fullRtmpUrl,
                'resolution' => $liveStream->format,
                'metadata' => [
                    'ingest_server_name' => $ingest['name'] ?? null,
                ],
            ],
            'provisioned_at' => now()->toIso8601String(),
        ];
    }

    protected function finalizeYoutubeDestination(StreamingDestination $destination, array $session): array
    {
        if (blank($session['broadcast_id'] ?? null)) {
            return [
                'destination_id' => $destination->id,
                'provider' => 'youtube',
                'status' => 'skipped',
                'message' => 'No broadcast id recorded',
            ];
        }

        $response = Http::withToken($destination->access_token)
            ->acceptJson()
            ->post(sprintf(
                'https://www.googleapis.com/youtube/v3/liveBroadcasts/transition?id=%s&broadcastStatus=complete&part=id,status',
                $session['broadcast_id']
            ))
            ->throw()
            ->json();

        return [
            'destination_id' => $destination->id,
            'provider' => 'youtube',
            'status' => 'completed',
            'broadcast_id' => $session['broadcast_id'],
            'youtube_status' => Arr::get($response, 'status.lifeCycleStatus'),
        ];
    }

    protected function finalizeMetaDestination(StreamingDestination $destination, array $session): array
    {
        if (blank($session['live_video_id'] ?? null)) {
            return [
                'destination_id' => $destination->id,
                'provider' => 'meta',
                'status' => 'skipped',
                'message' => 'No live video id recorded',
            ];
        }

        $graphVersion = config('services.meta.graph_version', 'v22.0');

        $response = Http::acceptJson()
            ->asForm()
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/live_videos',
                $graphVersion,
                $session['live_video_id']
            ), [
                'access_token' => $destination->access_token,
                'end_live_video' => 'true',
            ])
            ->throw()
            ->json();

        return [
            'destination_id' => $destination->id,
            'provider' => 'meta',
            'status' => 'completed',
            'live_video_id' => $session['live_video_id'],
            'meta_status' => $response['status'] ?? null,
        ];
    }

    protected function youtubeEncodingProfile(string $format): array
    {
        return match ($format) {
            '1080p' => ['1080p', '30fps'],
            default => ['720p', '30fps'],
        };
    }

    protected function buildFullRtmpUrl(?string $rtmpUrl, ?string $streamKey): ?string
    {
        if (blank($rtmpUrl)) {
            return null;
        }

        if (blank($streamKey)) {
            return $rtmpUrl;
        }

        return rtrim($rtmpUrl, '/').'/'.$streamKey;
    }

    protected function parseMetaStreamUrl(?string $secureStreamUrl): array
    {
        if (blank($secureStreamUrl)) {
            throw new \RuntimeException('Meta did not return a secure stream url');
        }

        $parts = parse_url($secureStreamUrl);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $streamKey = ltrim(preg_replace('#^/rtmp/#', '', $path), '/').$query;
        $rtmpUrl = sprintf(
            '%s://%s%s%s',
            $parts['scheme'] ?? 'rtmps',
            $parts['host'] ?? 'rtmp.facebook.com',
            isset($parts['port']) ? ':'.$parts['port'] : '',
            '/rtmp'
        );

        return [
            'rtmp_url' => $rtmpUrl,
            'stream_key' => $streamKey,
            'full_rtmp_url' => $secureStreamUrl,
        ];
    }

    protected function hydrateRuntimeCredentials(StreamingDestination $destination): StreamingDestination
    {
        $destination->loadMissing('connectedAccount');

        if (! $destination->connectedAccount) {
            return $destination;
        }

        $provider = $this->normalizeProvider($destination->type);

        if (! in_array($provider, ['youtube', 'twitch'], true)) {
            return $destination;
        }

        $account = $this->integrationService->refreshConnectedAccount($destination->connectedAccount);

        $destination->update([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'token_expires_at' => $account->token_expires_at,
            'is_valid' => true,
        ]);

        return $destination->fresh(['connectedAccount']);
    }

    protected function storeDestinationRuntimeMetadata(StreamingDestination $destination, array $session): void
    {
        $destination->update([
            'metadata' => array_merge($destination->metadata ?? [], [
                'last_provisioned_live_session' => Arr::except($session, ['output']),
                'last_provisioned_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    protected function normalizeProvider(string $type): string
    {
        return $type === 'facebook' ? 'meta' : $type;
    }
}
