<?php

namespace App\Modules\Projects\Services;

use App\Models\LiveStream;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class StreamRuntimeArtifactService
{
    public function writeArtifacts(LiveStream $liveStream): array
    {
        $runtimePath = $this->runtimePath($liveStream);

        File::ensureDirectoryExists($runtimePath);

        $studioSnapshot = $liveStream->metadata['studio_snapshot'] ?? [];
        $overlayText = $this->overlayText($studioSnapshot);
        $renderSignature = $this->renderSignature($studioSnapshot);
        $textSignature = $this->textSignature($studioSnapshot);
        $layerTextPaths = $this->writeLayerTextArtifacts($runtimePath, $studioSnapshot);

        $overlayPath = $runtimePath.'/overlay.txt';
        $studioPath = $runtimePath.'/studio.json';
        $manifestPath = $runtimePath.'/manifest.json';
        $commandPath = $runtimePath.'/command.json';
        $logPath = $runtimePath.'/worker.log';

        File::put($overlayPath, $overlayText);
        File::put($studioPath, json_encode($studioSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::put($manifestPath, json_encode([
            'live_stream_id' => $liveStream->id,
            'project_id' => $liveStream->project_id,
            'format' => $liveStream->format,
            'overlay_path' => $overlayPath,
            'studio_path' => $studioPath,
            'layer_text_paths' => $layerTextPaths,
            'render_signature' => $renderSignature,
            'source_signature' => $renderSignature,
            'text_signature' => $textSignature,
            'updated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'runtime_path' => $runtimePath,
            'overlay_path' => $overlayPath,
            'studio_path' => $studioPath,
            'manifest_path' => $manifestPath,
            'command_path' => $commandPath,
            'log_path' => $logPath,
            'layer_text_paths' => $layerTextPaths,
            'overlay_text' => $overlayText,
            'render_signature' => $renderSignature,
            'source_signature' => $renderSignature,
            'text_signature' => $textSignature,
        ];
    }

    public function runtimePath(LiveStream $liveStream): string
    {
        return storage_path('app/stream-workers/'.$liveStream->id);
    }

    protected function overlayText(array $studioSnapshot): string
    {
        $projectName = $studioSnapshot['project']['name'] ?? 'Live Show';
        $activeSceneName = $studioSnapshot['active_scene']['name'] ?? 'No Active Scene';
        $visibleTexts = collect($studioSnapshot['active_scene']['layers'] ?? [])
            ->filter(fn (array $layer): bool => ($layer['is_visible'] ?? false) && in_array($layer['type'] ?? null, ['text', 'countdown', 'overlay'], true))
            ->map(fn (array $layer): string => trim((string) ($layer['content'] ?? $layer['name'] ?? '')))
            ->filter()
            ->values()
            ->all();

        $lines = array_filter([
            $projectName,
            'Scene: '.$activeSceneName,
            $visibleTexts !== [] ? implode(' | ', $visibleTexts) : null,
            'LiveRuntime '.now()->format('H:i:s'),
        ]);

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    public function renderSignature(array $studioSnapshot): string
    {
        $activeScene = $studioSnapshot['active_scene'] ?? [];

        $payload = [
            'scene_id' => $activeScene['id'] ?? null,
            'layers' => collect($activeScene['layers'] ?? [])
                ->filter(fn (array $layer): bool => (bool) ($layer['is_visible'] ?? false))
                ->map(function (array $layer): array {
                    return [
                        'id' => $layer['id'] ?? null,
                        'type' => $layer['type'] ?? null,
                        'position' => Arr::only($layer['position'] ?? [], ['x', 'y', 'width', 'height']),
                        'settings' => Arr::except($layer['settings'] ?? [], ['content']),
                        'asset' => Arr::only($layer['asset'] ?? [], ['id', 'source_url', 'storage_path', 'format']),
                    ];
                })
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function sourceSignature(array $studioSnapshot): string
    {
        return $this->renderSignature($studioSnapshot);
    }

    public function textSignature(array $studioSnapshot): string
    {
        $payload = collect($studioSnapshot['active_scene']['layers'] ?? [])
            ->filter(fn (array $layer): bool => (bool) ($layer['is_visible'] ?? false) && in_array($layer['type'] ?? null, ['text', 'overlay', 'countdown'], true))
            ->map(function (array $layer): array {
                return [
                    'id' => $layer['id'] ?? null,
                    'type' => $layer['type'] ?? null,
                    'content' => $layer['content'] ?? null,
                    'settings' => Arr::only($layer['settings'] ?? [], ['ends_at']),
                ];
            })
            ->values()
            ->all();

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    protected function writeLayerTextArtifacts(string $runtimePath, array $studioSnapshot): array
    {
        $paths = [];

        foreach ($studioSnapshot['active_scene']['layers'] ?? [] as $layer) {
            if (! ($layer['is_visible'] ?? false) || ! in_array($layer['type'] ?? null, ['text', 'overlay'], true)) {
                continue;
            }

            $layerId = (int) ($layer['id'] ?? 0);

            if ($layerId <= 0) {
                continue;
            }

            $path = $runtimePath."/layer-{$layerId}.txt";
            File::put($path, trim((string) ($layer['content'] ?? '')).PHP_EOL);
            $paths[$layerId] = $path;
        }

        return $paths;
    }

    protected function countdownSnapshot(array $layer): ?string
    {
        $endsAt = $layer['settings']['ends_at'] ?? null;

        if (blank($endsAt)) {
            return null;
        }

        try {
            $remaining = max(0, now()->diffInSeconds(Carbon::parse($endsAt), false));
        } catch (\Throwable) {
            return null;
        }

        return gmdate('H:i:s', $remaining);
    }
}
