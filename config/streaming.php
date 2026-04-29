<?php

return [
    'engine' => [
        'driver' => env('STREAM_ENGINE_DRIVER', 'ffmpeg'),
        'base_url' => env('STREAM_ENGINE_BASE_URL'),
        'api_key' => env('STREAM_ENGINE_API_KEY'),
        'api_secret' => env('STREAM_ENGINE_API_SECRET'),
        'output_mode' => env('STREAM_OUTPUT_MODE', 'rtmp'),
        'callback_secret' => env('MEDIA_WORKER_CALLBACK_SECRET'),
    ],

    'ffmpeg' => [
        'bin' => env('FFMPEG_BIN', '/opt/homebrew/bin/ffmpeg'),
        'ffprobe_bin' => env('FFPROBE_BIN', '/opt/homebrew/bin/ffprobe'),
        'font_family' => env('FFMPEG_FONT_FAMILY', 'Sans'),
        'preset' => env('FFMPEG_PRESET', 'veryfast'),
        'video_bitrate' => env('FFMPEG_VIDEO_BITRATE', '4500k'),
        'audio_bitrate' => env('FFMPEG_AUDIO_BITRATE', '128k'),
        'fps' => (int) env('FFMPEG_FPS', 30),
        'gop' => (int) env('FFMPEG_GOP', 60),
    ],

    'mediasoup' => [
        'enabled' => env('MEDIASOUP_ENABLED', true),
        'signaling_url' => env('MEDIASOUP_SIGNALING_URL', 'ws://127.0.0.1:4010'),
        'listen_host' => env('MEDIASOUP_LISTEN_HOST', '127.0.0.1'),
        'listen_port' => (int) env('MEDIASOUP_LISTEN_PORT', 4010),
        'rtc_listen_ip' => env('MEDIASOUP_RTC_LISTEN_IP', '127.0.0.1'),
        'rtc_announced_address' => env('MEDIASOUP_RTC_ANNOUNCED_ADDRESS'),
        'rtc_min_port' => (int) env('MEDIASOUP_RTC_MIN_PORT', 40000),
        'rtc_max_port' => (int) env('MEDIASOUP_RTC_MAX_PORT', 40199),
        'log_level' => env('MEDIASOUP_LOG_LEVEL', 'warn'),
        'room_token_ttl' => (int) env('MEDIASOUP_ROOM_TOKEN_TTL', 7200),
    ],

    'turn' => [
        'urls' => array_values(array_filter(array_map(
            static fn (string $url): string => trim($url),
            explode(',', (string) env('TURN_URLS', ''))
        ))),
        'username' => env('TURN_USERNAME'),
        'credential' => env('TURN_CREDENTIAL'),
        'shared_secret' => env('TURN_SHARED_SECRET'),
    ],

    'integrations' => [
        'frontend_redirect' => env('INTEGRATIONS_FRONTEND_REDIRECT', env('APP_URL', 'http://localhost:8000')),
        'providers' => ['youtube', 'facebook', 'twitch', 'google_drive', 'dropbox', 'slack'],
    ],

    'providers' => [
        'youtube' => [
            'privacy_status' => env('YOUTUBE_LIVE_PRIVACY_STATUS', 'unlisted'),
            'auto_start' => env('YOUTUBE_LIVE_AUTO_START', true),
            'auto_stop' => env('YOUTUBE_LIVE_AUTO_STOP', true),
        ],
        'meta' => [
            'status' => env('META_LIVE_STATUS', 'LIVE_NOW'),
            'live_video_preset' => env('META_LIVE_VIDEO_PRESET'),
        ],
        'twitch' => [
            'fallback_ingest_url' => env('TWITCH_FALLBACK_INGEST_URL', 'rtmp://live.twitch.tv/app/{stream_key}'),
        ],
    ],
];
