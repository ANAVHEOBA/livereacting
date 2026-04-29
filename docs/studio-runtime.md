# Studio Runtime

This repository now ships a Laravel API layer plus a mediasoup guest signaling server.
It also includes a local FFmpeg worker runtime for provider egress.

## Core scripts

- `pnpm media:server`
  Starts the mediasoup signaling runtime from `server/mediasoup/index.mjs`.
- `pnpm dev:studio`
  Runs Vite and the mediasoup signaling server together.
- `php artisan serve`
  Serves the Laravel application and API routes.
- `php artisan streams:run-worker {liveStreamId}`
  Runs the local FFmpeg worker for a provisioned live stream.
- `php artisan integrations:validate {accountId?}`
  Validates one or all connected provider accounts against the upstream APIs.
- `php artisan studio:health`
  Prints the backend readiness report for FFmpeg, storage, mediasoup, and provider configuration.

## Environment variables

The streaming and guest stack reads from:

- `STREAM_ENGINE_DRIVER`
- `STREAM_ENGINE_BASE_URL`
- `STREAM_ENGINE_API_KEY`
- `STREAM_ENGINE_API_SECRET`
- `STREAM_OUTPUT_MODE`
- `MEDIA_WORKER_CALLBACK_SECRET`
- `FFMPEG_BIN`
- `FFPROBE_BIN`
- `FFMPEG_FONT_FAMILY`
- `FFMPEG_PRESET`
- `FFMPEG_VIDEO_BITRATE`
- `FFMPEG_AUDIO_BITRATE`
- `FFMPEG_FPS`
- `FFMPEG_GOP`
- `MEDIASOUP_ENABLED`
- `MEDIASOUP_SIGNALING_URL`
- `MEDIASOUP_LISTEN_HOST`
- `MEDIASOUP_LISTEN_PORT`
- `MEDIASOUP_RTC_LISTEN_IP`
- `MEDIASOUP_RTC_ANNOUNCED_ADDRESS`
- `MEDIASOUP_RTC_MIN_PORT`
- `MEDIASOUP_RTC_MAX_PORT`
- `MEDIASOUP_LOG_LEVEL`
- `MEDIASOUP_ROOM_TOKEN_TTL`
- `TURN_URLS`
- `TURN_USERNAME`
- `TURN_CREDENTIAL`
- `TURN_SHARED_SECRET`
- `SLACK_CLIENT_ID`
- `SLACK_CLIENT_SECRET`
- `SLACK_REDIRECT_URL`
- `YOUTUBE_CLIENT_ID`
- `YOUTUBE_CLIENT_SECRET`
- `YOUTUBE_REDIRECT_URL`
- `META_APP_ID`
- `META_APP_SECRET`
- `META_REDIRECT_URL`
- `TWITCH_CLIENT_ID`
- `TWITCH_CLIENT_SECRET`
- `TWITCH_REDIRECT_URL`
- `GOOGLE_DRIVE_CLIENT_ID`
- `GOOGLE_DRIVE_CLIENT_SECRET`
- `GOOGLE_DRIVE_REDIRECT_URL`
- `DROPBOX_APP_KEY`
- `DROPBOX_APP_SECRET`
- `DROPBOX_REDIRECT_URL`
- `INTEGRATIONS_FRONTEND_REDIRECT`

## Laravel endpoints

- `GET /api/studio/config`
  Returns the current runtime profile for FFmpeg, mediasoup, TURN, destinations, and asset features.
- `GET /api/projects/{id}/guests`
  Returns the guest room plus host signaling payload.
- `POST /api/guest-invites/{token}/accept`
  Accepts a guest invite and returns a mediasoup signaling token.
- `GET /api/integrations`
  Returns provider catalog plus the user's connected external accounts.
- `GET /api/integrations/{provider}/authorize`
  Builds an OAuth authorization URL for Google Drive, YouTube, Twitch, Meta/Facebook, Dropbox, or Slack.
- `GET /api/integrations/{provider}/callback`
  Completes OAuth and stores the connected account.
- `GET /api/integrations/{provider}/assets`
  Lists provider-backed remote assets for Google Drive, Dropbox, and YouTube.
- `GET /api/integrations/{provider}/validate`
  Validates a connected provider account against the upstream API and stores the last validation result in account metadata.
- `GET /api/integrations/{provider}/destinations`
  Lists streamable destinations for YouTube, Twitch, and Meta/Facebook.
- `POST /api/integrations/{provider}/destinations`
  Creates a first-class `streaming_destinations` record from a connected provider account.
- `POST /api/integrations/{provider}/imports`
  Starts a provider-backed media import through the existing library pipeline.
- `POST /api/integrations/slack/notify-test`
  Sends a Slack test notification using a stored workspace token.
- `POST /api/destinations/{id}/probe`
  Probes a destination against the provider API or RTMP payload and stores the last validation result in destination metadata.
- `GET /api/studio/health`
  Returns backend readiness for FFmpeg, ffprobe, runtime storage, mediasoup config, and provider configuration.

## Native destination provisioning

When `POST /api/projects/{id}/live` runs, the backend now provisions provider-specific egress targets before marking the stream live.

- `rtmp`
  Uses the destination's stored `rtmp_url` and `stream_key` directly.
- `youtube`
  Creates a native YouTube stream via `liveStreams.insert`, creates a broadcast via `liveBroadcasts.insert`, binds them with `liveBroadcasts.bind`, and stores the resulting ingest address plus stream key in the live stream metadata.
- `facebook` / Meta
  Creates a live video session on the target Page and stores the returned secure RTMPS ingest target in the live stream metadata.
- `twitch`
  Resolves the broadcaster's stream key and an ingest server, then builds the RTMP output target for the FFmpeg egress plan.

## Local worker runtime

When `STREAM_ENGINE_DRIVER=ffmpeg`, the backend now:

- writes runtime artifacts under `storage/app/stream-workers/{live_stream_id}`
- stores `studio.json`, `overlay.txt`, `manifest.json`, `command.json`, and `worker.log`
- detaches a local `php artisan streams:run-worker {id}` process
- builds a multi-layer FFmpeg filter graph from the active scene
- composites visible video and image layers using scene positions, fit rules, and opacity
- renders text, overlay, and countdown layers with live-reload text artifacts
- mixes dedicated audio layers into the outgoing program audio
- fans the encoded stream out to every provisioned RTMP destination

When `STREAM_ENGINE_DRIVER=fake`, the same artifact generation and command planning still run, but the worker stays in-process for tests and dry runs.

When `DELETE /api/projects/{id}/live` runs:

- YouTube broadcasts are completed through `liveBroadcasts.transition`.
- Meta live videos are ended through the live video termination edge.
- RTMP and Twitch outputs are local no-op finalizations because their ingest sessions are encoder-driven rather than broadcast-object-driven.

When `POST /api/projects/{id}/sync` runs against an active stream:

- the live stream metadata is refreshed
- the runtime artifacts are rewritten
- if the active visual source changed, the local worker is restarted with a new FFmpeg plan
- if the scene graph layout or layer styling changed, the local worker is restarted with a new FFmpeg plan
- if only overlay text changed, the worker keeps running and FFmpeg reloads `overlay.txt`

## Mediasoup signaling actions

The WebSocket server accepts JSON messages with `{ action, requestId, data }`.

Supported actions:

- `joinRoom`
- `createWebRtcTransport`
- `connectWebRtcTransport`
- `produce`
- `consume`
- `resumeConsumer`
- `produceData`
- `consumeData`

Server notifications:

- `peerJoined`
- `peerLeft`
- `newProducer`
- `newDataProducer`
- `consumerClosed`

## References

- mediasoup communication model:
  https://mediasoup.org/documentation/v3/communication-between-client-and-server/
- mediasoup server API:
  https://mediasoup.org/documentation/v3/mediasoup/api/
- mediasoup-client API:
  https://mediasoup.org/documentation/v3/mediasoup-client/api/
- Google OAuth 2.0 web server flow:
  https://developers.google.com/identity/protocols/oauth2/web-server
- Google Drive files.list:
  https://developers.google.com/drive/api/reference/rest/v3/files/list
- YouTube OAuth:
  https://developers.google.com/youtube/v3/guides/authentication
- YouTube channel implementation guide:
  https://developers.google.com/youtube/v3/guides/implementation/channels
- YouTube liveBroadcasts.insert:
  https://developers.google.com/youtube/v3/live/docs/liveBroadcasts/insert
- YouTube liveStreams.insert:
  https://developers.google.com/youtube/v3/live/docs/liveStreams/insert
- YouTube liveBroadcasts.bind:
  https://developers.google.com/youtube/v3/live/docs/liveBroadcasts/bind
- YouTube liveBroadcasts.transition:
  https://developers.google.com/youtube/v3/live/docs/liveBroadcasts/transition
- Twitch authentication:
  https://dev.twitch.tv/docs/authentication
- Twitch video broadcast overview:
  https://dev.twitch.tv/docs/video-broadcast/
- Twitch get stream key:
  https://dev.twitch.tv/docs/api/reference
- Twitch ingest servers:
  https://dev.twitch.tv/docs/video-broadcast/reference/
- Slack OAuth v2:
  https://api.slack.com/authentication/oauth-v2
- Slack auth.test:
  https://api.slack.com/methods/auth.test
- Dropbox OAuth guide:
  https://developers.dropbox.com/oauth-guide
- Meta Facebook Login for Web:
  https://developers.facebook.com/docs/facebook-login/web
