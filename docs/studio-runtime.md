# Studio Runtime

This repository now ships a Laravel API layer plus a mediasoup guest signaling server.

## Core scripts

- `pnpm media:server`
  Starts the mediasoup signaling runtime from `server/mediasoup/index.mjs`.
- `pnpm dev:studio`
  Runs Vite and the mediasoup signaling server together.
- `php artisan serve`
  Serves the Laravel application and API routes.

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

## Laravel endpoints

- `GET /api/studio/config`
  Returns the current runtime profile for FFmpeg, mediasoup, TURN, destinations, and asset features.
- `GET /api/projects/{id}/guests`
  Returns the guest room plus host signaling payload.
- `POST /api/guest-invites/{token}/accept`
  Accepts a guest invite and returns a mediasoup signaling token.

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
