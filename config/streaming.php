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
];
