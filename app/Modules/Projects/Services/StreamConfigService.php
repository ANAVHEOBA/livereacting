<?php

namespace App\Modules\Projects\Services;

class StreamConfigService
{
    public function getStudioConfig(): array
    {
        return [
            'engine' => [
                'driver' => config('streaming.engine.driver'),
                'output_mode' => config('streaming.engine.output_mode'),
                'base_url' => config('streaming.engine.base_url'),
            ],
            'ffmpeg' => [
                'bin' => config('streaming.ffmpeg.bin'),
                'ffprobe_bin' => config('streaming.ffmpeg.ffprobe_bin'),
            ],
            'mediasoup' => [
                'enabled' => config('streaming.mediasoup.enabled'),
                'signaling_url' => config('streaming.mediasoup.signaling_url'),
                'listen_host' => config('streaming.mediasoup.listen_host'),
                'listen_port' => config('streaming.mediasoup.listen_port'),
                'rtc_listen_ip' => config('streaming.mediasoup.rtc_listen_ip'),
                'rtc_announced_address' => config('streaming.mediasoup.rtc_announced_address'),
                'rtc_min_port' => config('streaming.mediasoup.rtc_min_port'),
                'rtc_max_port' => config('streaming.mediasoup.rtc_max_port'),
                'log_level' => config('streaming.mediasoup.log_level'),
            ],
            'turn' => [
                'urls' => config('streaming.turn.urls'),
                'username' => config('streaming.turn.username'),
            ],
            'destinations' => [
                'providers' => ['youtube', 'facebook', 'twitch', 'rtmp'],
            ],
            'assets' => [
                'import_sources' => ['upload', 'google_drive', 'dropbox', 'youtube', 'url'],
                'playlist_enabled' => true,
                'scene_templates_enabled' => true,
            ],
            'integrations' => [
                'providers' => config('streaming.integrations.providers'),
                'frontend_redirect' => config('streaming.integrations.frontend_redirect'),
            ],
        ];
    }
}
