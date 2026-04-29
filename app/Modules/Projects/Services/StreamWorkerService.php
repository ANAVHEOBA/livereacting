<?php

namespace App\Modules\Projects\Services;

use App\Models\LiveStream;
use App\Modules\Projects\Repositories\LiveStreamRepository;
use App\Modules\Projects\Repositories\ProjectRepository;
use Symfony\Component\Process\Process;

class StreamWorkerService
{
    public function __construct(
        protected StreamRuntimeArtifactService $artifactService,
        protected FfmpegCommandBuilderService $commandBuilder,
        protected LiveStreamRepository $liveStreamRepository,
        protected ProjectRepository $projectRepository
    ) {}

    public function start(LiveStream $liveStream): array
    {
        $outputs = $liveStream->metadata['egress']['outputs'] ?? [];

        if ($outputs === []) {
            throw new \RuntimeException('No egress outputs are available for this live stream');
        }

        $artifacts = $this->artifactService->writeArtifacts($liveStream);
        $command = $this->commandBuilder->build($liveStream, $artifacts, $outputs);
        $driver = config('streaming.engine.driver', 'ffmpeg');
        $restartCount = (int) (($liveStream->metadata['worker']['restart_count'] ?? 0));

        file_put_contents($artifacts['command_path'], json_encode($command, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($driver === 'fake') {
            return [
                'driver' => 'fake',
                'status' => 'running',
                'pid' => null,
                'runtime_path' => $artifacts['runtime_path'],
                'overlay_path' => $artifacts['overlay_path'],
                'studio_path' => $artifacts['studio_path'],
                'manifest_path' => $artifacts['manifest_path'],
                'command_path' => $artifacts['command_path'],
                'log_path' => $artifacts['log_path'],
                'layer_text_paths' => $artifacts['layer_text_paths'],
                'command' => $command,
                'render_signature' => $artifacts['render_signature'],
                'source_signature' => $artifacts['source_signature'],
                'text_signature' => $artifacts['text_signature'],
                'restart_count' => $restartCount,
                'started_at' => now()->toIso8601String(),
            ];
        }

        $pid = $this->launchDetachedWorker($liveStream, $artifacts['log_path']);

        return [
            'driver' => 'ffmpeg',
            'status' => 'starting',
            'pid' => $pid,
            'runtime_path' => $artifacts['runtime_path'],
            'overlay_path' => $artifacts['overlay_path'],
            'studio_path' => $artifacts['studio_path'],
            'manifest_path' => $artifacts['manifest_path'],
            'command_path' => $artifacts['command_path'],
            'log_path' => $artifacts['log_path'],
            'layer_text_paths' => $artifacts['layer_text_paths'],
            'command' => $command,
            'render_signature' => $artifacts['render_signature'],
            'source_signature' => $artifacts['source_signature'],
            'text_signature' => $artifacts['text_signature'],
            'restart_count' => $restartCount,
            'started_at' => now()->toIso8601String(),
        ];
    }

    public function stop(LiveStream $liveStream): array
    {
        $worker = $liveStream->metadata['worker'] ?? [];

        if ($worker === []) {
            return ['status' => 'noop'];
        }

        $worker['stop_requested_at'] = now()->toIso8601String();
        $worker['status'] = 'stopping';

        if (($worker['driver'] ?? null) === 'fake') {
            $worker['stopped_at'] = now()->toIso8601String();
            $worker['status'] = 'stopped';

            return $worker;
        }

        $pid = (int) ($worker['pid'] ?? 0);

        if ($pid > 0) {
            $this->terminateProcess($pid);
        }

        $worker['stopped_at'] = now()->toIso8601String();

        return $worker;
    }

    public function sync(LiveStream $liveStream): array
    {
        $worker = $liveStream->metadata['worker'] ?? [];

        if ($worker === []) {
            return [
                'updated' => false,
                'restart_required' => false,
            ];
        }

        $artifacts = $this->artifactService->writeArtifacts($liveStream);
        $restartRequired = ($worker['render_signature'] ?? $worker['source_signature'] ?? null) !== $artifacts['render_signature'];

        $worker['overlay_path'] = $artifacts['overlay_path'];
        $worker['studio_path'] = $artifacts['studio_path'];
        $worker['manifest_path'] = $artifacts['manifest_path'];
        $worker['layer_text_paths'] = $artifacts['layer_text_paths'];
        $worker['render_signature_next'] = $artifacts['render_signature'];
        $worker['source_signature_next'] = $artifacts['source_signature'];
        $worker['text_signature'] = $artifacts['text_signature'];
        $worker['last_synced_at'] = now()->toIso8601String();

        if (! $restartRequired) {
            $worker['render_signature'] = $artifacts['render_signature'];
            $worker['source_signature'] = $artifacts['source_signature'];
        }

        return [
            'updated' => true,
            'restart_required' => $restartRequired,
            'worker' => $worker,
        ];
    }

    public function restart(LiveStream $liveStream): array
    {
        $currentWorker = $liveStream->metadata['worker'] ?? [];
        $restartCount = (int) ($currentWorker['restart_count'] ?? 0) + 1;

        $stoppedWorker = $this->stop($liveStream);
        $metadata = array_merge($liveStream->metadata ?? [], [
            'worker' => array_merge($stoppedWorker, [
                'status' => 'restarting',
                'restart_count' => $restartCount,
            ]),
        ]);

        $this->liveStreamRepository->update($liveStream, [
            'metadata' => $metadata,
        ]);

        $reloaded = $liveStream->fresh();
        $startedWorker = $this->start($reloaded);
        $startedWorker['restart_count'] = $restartCount;

        return $startedWorker;
    }

    public function run(LiveStream $liveStream): int
    {
        $outputs = $liveStream->metadata['egress']['outputs'] ?? [];
        $artifacts = $this->artifactService->writeArtifacts($liveStream);
        $command = $this->commandBuilder->build($liveStream, $artifacts, $outputs);

        file_put_contents($artifacts['command_path'], json_encode($command, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $worker = array_merge($liveStream->metadata['worker'] ?? [], [
            'status' => 'running',
            'command' => $command,
            'render_signature' => $artifacts['render_signature'],
            'source_signature' => $artifacts['source_signature'],
            'text_signature' => $artifacts['text_signature'],
            'overlay_path' => $artifacts['overlay_path'],
            'studio_path' => $artifacts['studio_path'],
            'manifest_path' => $artifacts['manifest_path'],
            'command_path' => $artifacts['command_path'],
            'log_path' => $artifacts['log_path'],
            'layer_text_paths' => $artifacts['layer_text_paths'],
            'started_at' => $liveStream->metadata['worker']['started_at'] ?? now()->toIso8601String(),
            'running_at' => now()->toIso8601String(),
        ]);

        $this->liveStreamRepository->update($liveStream, [
            'metadata' => array_merge($liveStream->metadata ?? [], [
                'worker' => $worker,
            ]),
        ]);

        $process = new Process($command, base_path());
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $process->run(function (string $type, string $buffer) use ($artifacts): void {
            file_put_contents($artifacts['log_path'], $buffer, FILE_APPEND);
        });

        $freshLiveStream = $liveStream->fresh();
        $freshWorker = $freshLiveStream?->metadata['worker'] ?? [];
        $stopRequested = ! empty($freshWorker['stop_requested_at']);
        $exitCode = $process->getExitCode() ?? 1;

        $freshWorker['finished_at'] = now()->toIso8601String();
        $freshWorker['status'] = $stopRequested ? 'stopped' : ($exitCode === 0 ? 'completed' : 'failed');
        $freshWorker['last_exit_code'] = $exitCode;

        $payload = [
            'metadata' => array_merge($freshLiveStream?->metadata ?? [], [
                'worker' => $freshWorker,
            ]),
        ];

        if (! $stopRequested && $exitCode !== 0) {
            $payload['status'] = 'failed';
            $payload['error_message'] = 'FFmpeg worker exited unexpectedly';

            $project = $freshLiveStream?->project;

            if ($project) {
                $this->projectRepository->update($project, [
                    'status' => 'completed',
                    'active_live_id' => null,
                ]);
            }
        }

        $this->liveStreamRepository->update($freshLiveStream, $payload);

        return $exitCode;
    }

    protected function launchDetachedWorker(LiveStream $liveStream, string $logPath): int
    {
        $command = sprintf(
            '%s %s streams:run-worker %d > %s 2>&1 & echo $!',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            $liveStream->id,
            escapeshellarg($logPath)
        );

        exec($command, $output, $exitCode);

        $pid = isset($output[0]) ? (int) trim($output[0]) : 0;

        if ($exitCode !== 0 || $pid <= 0) {
            throw new \RuntimeException('Unable to start FFmpeg worker process');
        }

        return $pid;
    }

    protected function terminateProcess(int $pid): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, SIGTERM);
            usleep(250000);

            if (@posix_kill($pid, 0)) {
                @posix_kill($pid, SIGKILL);
            }

            return;
        }

        exec(sprintf('kill -TERM %d', $pid));
        usleep(250000);
        exec(sprintf('kill -KILL %d', $pid));
    }
}
