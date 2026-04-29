<?php

namespace App\Modules\Projects\Services;

use Symfony\Component\Process\Process;

class StudioHealthService
{
    public function report(): array
    {
        $runtimePath = storage_path('app/stream-workers');

        if (! is_dir($runtimePath)) {
            @mkdir($runtimePath, 0777, true);
        }

        return [
            'status' => 'ok',
            'checked_at' => now()->toIso8601String(),
            'engine' => [
                'driver' => config('streaming.engine.driver'),
                'output_mode' => config('streaming.engine.output_mode'),
            ],
            'ffmpeg' => $this->binaryHealth(
                (string) config('streaming.ffmpeg.bin'),
                ['-hide_banner', '-filters'],
                'drawtext'
            ),
            'ffprobe' => $this->binaryHealth(
                (string) config('streaming.ffmpeg.ffprobe_bin'),
                ['-version']
            ),
            'runtime_storage' => [
                'path' => $runtimePath,
                'exists' => is_dir($runtimePath),
                'writable' => is_writable($runtimePath),
            ],
            'mediasoup' => [
                'enabled' => (bool) config('streaming.mediasoup.enabled'),
                'signaling_url' => config('streaming.mediasoup.signaling_url'),
            ],
            'providers' => collect(config('streaming.integrations.providers', []))
                ->mapWithKeys(fn (string $provider): array => [
                    $provider => ['configured' => $this->providerConfigured($provider)],
                ])
                ->all(),
        ];
    }

    protected function binaryHealth(string $binary, array $args, ?string $contains = null): array
    {
        if ($binary === '' || ! file_exists($binary)) {
            return [
                'path' => $binary,
                'exists' => false,
                'executable' => false,
                'healthy' => false,
                'contains_expected_feature' => false,
            ];
        }

        $process = new Process(array_merge([$binary], $args), base_path());
        $process->setTimeout(5);

        try {
            $process->run();
            $output = $process->getOutput().$process->getErrorOutput();
            $healthy = $process->isSuccessful();
        } catch (\Throwable $e) {
            return [
                'path' => $binary,
                'exists' => true,
                'executable' => is_executable($binary),
                'healthy' => false,
                'contains_expected_feature' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'path' => $binary,
            'exists' => true,
            'executable' => is_executable($binary),
            'healthy' => $healthy,
            'contains_expected_feature' => $contains ? str_contains($output, $contains) : null,
        ];
    }

    protected function providerConfigured(string $provider): bool
    {
        return match ($provider) {
            'youtube' => filled(config('services.youtube.client_id'))
                && filled(config('services.youtube.client_secret'))
                && filled(config('services.youtube.redirect')),
            'facebook' => filled(config('services.meta.app_id'))
                && filled(config('services.meta.app_secret'))
                && filled(config('services.meta.redirect')),
            'twitch' => filled(config('services.twitch.client_id'))
                && filled(config('services.twitch.client_secret'))
                && filled(config('services.twitch.redirect')),
            'google_drive' => filled(config('services.google_drive.client_id'))
                && filled(config('services.google_drive.client_secret'))
                && filled(config('services.google_drive.redirect')),
            'dropbox' => filled(config('services.dropbox.app_key'))
                && filled(config('services.dropbox.app_secret'))
                && filled(config('services.dropbox.redirect')),
            'slack' => filled(config('services.slack.client_id'))
                && filled(config('services.slack.client_secret'))
                && filled(config('services.slack.redirect')),
            default => false,
        };
    }
}
