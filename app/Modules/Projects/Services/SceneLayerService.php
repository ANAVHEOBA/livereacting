<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\Scene;
use App\Models\SceneLayer;
use App\Modules\Projects\Repositories\SceneLayerRepository;
use App\Modules\Videos\Repositories\FileRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SceneLayerService
{
    public function __construct(
        protected SceneLayerRepository $sceneLayerRepository,
        protected FileRepository $fileRepository,
        protected HistoryService $historyService
    ) {}

    public function getSceneLayers(Scene $scene): Collection
    {
        return $this->sceneLayerRepository->getByScene($scene->id);
    }

    public function getLayer(Scene $scene, int $layerId): ?SceneLayer
    {
        return $this->sceneLayerRepository->findByIdAndScene($layerId, $scene->id);
    }

    public function createLayer(Project $project, Scene $scene, int $userId, array $data): SceneLayer
    {
        $payload = $this->buildLayerPayload($userId, null, $data);

        $layer = $this->sceneLayerRepository->create([
            'scene_id' => $scene->id,
            'user_id' => $userId,
            'file_id' => $payload['file_id'],
            'type' => $payload['type'],
            'name' => $payload['name'],
            'content' => $payload['content'],
            'sort_order' => $this->sceneLayerRepository->getMaxSortOrder($scene->id) + 1,
            'is_visible' => $payload['is_visible'],
            'position' => $payload['position'],
            'settings' => $payload['settings'],
        ]);

        $this->historyService->logAction(
            $project,
            'scene_layer_created',
            'Scene layer created',
            [
                'scene_id' => $scene->id,
                'layer_id' => $layer->id,
                'layer_type' => $layer->type,
                'layer_name' => $layer->name,
            ]
        );

        return $layer->fresh('file');
    }

    public function updateLayer(Project $project, Scene $scene, SceneLayer $layer, int $userId, array $data): SceneLayer
    {
        $payload = $this->buildLayerPayload($userId, $layer, $data);

        $this->sceneLayerRepository->update($layer, $payload);

        $updatedLayer = $layer->fresh('file');

        $this->historyService->logAction(
            $project,
            'scene_layer_updated',
            'Scene layer updated',
            [
                'scene_id' => $scene->id,
                'layer_id' => $updatedLayer->id,
                'fields' => array_keys($data),
            ]
        );

        return $updatedLayer;
    }

    public function reorderLayers(Project $project, Scene $scene, array $layerIds): Collection
    {
        $layers = $this->sceneLayerRepository->getByScene($scene->id);
        $currentIds = $layers->pluck('id')->sort()->values()->all();
        $requestedIds = collect($layerIds)->sort()->values()->all();

        if ($currentIds !== $requestedIds) {
            throw new \Exception('Layer reorder payload must contain every scene layer exactly once');
        }

        DB::transaction(function () use ($layerIds, $scene) {
            foreach (array_values($layerIds) as $index => $layerId) {
                $layer = $this->sceneLayerRepository->findByIdAndScene($layerId, $scene->id);

                $this->sceneLayerRepository->update($layer, [
                    'sort_order' => $index + 1,
                ]);
            }
        });

        $this->historyService->logAction(
            $project,
            'scene_layers_reordered',
            'Scene layers reordered',
            [
                'scene_id' => $scene->id,
            ]
        );

        return $this->sceneLayerRepository->getByScene($scene->id);
    }

    public function deleteLayer(Project $project, Scene $scene, SceneLayer $layer): void
    {
        $this->sceneLayerRepository->delete($layer);

        $remainingLayers = $this->sceneLayerRepository->getByScene($scene->id)->values();

        foreach ($remainingLayers as $index => $remainingLayer) {
            if ($remainingLayer->sort_order !== $index + 1) {
                $this->sceneLayerRepository->update($remainingLayer, [
                    'sort_order' => $index + 1,
                ]);
            }
        }

        $this->historyService->logAction(
            $project,
            'scene_layer_deleted',
            'Scene layer deleted',
            [
                'scene_id' => $scene->id,
                'layer_id' => $layer->id,
                'layer_name' => $layer->name,
            ]
        );
    }

    protected function buildLayerPayload(int $userId, ?SceneLayer $layer, array $data): array
    {
        $type = $data['type'] ?? $layer?->type;
        $fileId = array_key_exists('file_id', $data) ? $data['file_id'] : $layer?->file_id;
        $content = array_key_exists('content', $data) ? $data['content'] : $layer?->content;
        $incomingSettings = array_key_exists('settings', $data) ? ($data['settings'] ?? []) : ($layer?->settings ?? []);
        $incomingPosition = array_key_exists('position', $data) ? ($data['position'] ?? []) : ($layer?->position ?? []);

        if (! $type) {
            throw new \Exception('Layer type is required');
        }

        $settings = $this->normalizeSettings($type, $incomingSettings, $layer?->settings ?? []);
        $position = $this->normalizePosition($type, $incomingPosition, $layer?->position ?? []);

        $this->validateLayerState($userId, $type, $fileId, $content, $settings);

        return [
            'file_id' => $this->isFileBackedType($type) ? $fileId : null,
            'type' => $type,
            'name' => $data['name']
                ?? $layer?->name
                ?? $this->defaultLayerName($type, $fileId, $userId),
            'content' => $this->storesContent($type) ? $content : null,
            'is_visible' => $data['is_visible'] ?? $layer?->is_visible ?? true,
            'position' => ! empty($position) ? $position : ($layer?->position ?? $this->defaultPosition($type)),
            'settings' => ! empty($settings) ? $settings : ($layer?->settings ?? []),
        ];
    }

    protected function validateLayerState(int $userId, string $type, ?int $fileId, ?string $content, array $settings): void
    {
        if ($this->isFileBackedType($type)) {
            $file = $fileId ? $this->fileRepository->findByIdAndUser($fileId, $userId) : null;

            if (! $file) {
                throw new \Exception('Layer asset not found');
            }

            if (! $file->isReady()) {
                throw new \Exception('Layer asset must be ready before it can be used in a scene');
            }

            if ($file->type !== $type) {
                throw new \Exception("Layer asset type mismatch. Expected {$type} file");
            }

            $this->validateVisualAndAudioSettings($type, $settings);

            return;
        }

        if (in_array($type, ['text', 'overlay'], true) && blank($content)) {
            throw new \Exception('Text and overlay layers require content');
        }

        if ($type === 'countdown' && blank($settings['ends_at'] ?? null)) {
            throw new \Exception('Countdown layers require settings.ends_at');
        }

        if ($type === 'countdown' && ! strtotime((string) $settings['ends_at'])) {
            throw new \Exception('Countdown layers require a valid settings.ends_at timestamp');
        }

        if (in_array($type, ['text', 'overlay', 'countdown'], true)) {
            $this->validateTextSettings($settings);
        }
    }

    protected function isFileBackedType(string $type): bool
    {
        return in_array($type, ['video', 'audio', 'image'], true);
    }

    protected function storesContent(string $type): bool
    {
        return in_array($type, ['text', 'overlay'], true);
    }

    protected function defaultLayerName(string $type, ?int $fileId, int $userId): string
    {
        if ($fileId) {
            $file = $this->fileRepository->findByIdAndUser($fileId, $userId);

            if ($file) {
                return $file->name;
            }
        }

        return Str::headline($type).' Layer';
    }

    protected function defaultPosition(string $type): array
    {
        return match ($type) {
            'video', 'image', 'overlay' => ['x' => 0, 'y' => 0, 'width' => 1920, 'height' => 1080],
            'audio' => [],
            'countdown' => ['x' => 1500, 'y' => 40, 'width' => 320, 'height' => 80],
            default => ['x' => 40, 'y' => 40, 'width' => 600, 'height' => 120],
        };
    }

    protected function normalizePosition(string $type, array $position, array $fallback): array
    {
        if ($type === 'audio') {
            return [];
        }

        $base = array_merge($this->defaultPosition($type), $fallback, $position);

        return [
            'x' => (int) ($base['x'] ?? 0),
            'y' => (int) ($base['y'] ?? 0),
            'width' => max(1, (int) ($base['width'] ?? 1)),
            'height' => max(1, (int) ($base['height'] ?? 1)),
        ];
    }

    protected function normalizeSettings(string $type, array $settings, array $fallback): array
    {
        $base = array_merge($this->defaultSettings($type), $fallback, $settings);

        return match ($type) {
            'video', 'image' => [
                'fit' => $base['fit'] ?? 'cover',
                'opacity' => $this->normalizeRatio($base['opacity'] ?? 1),
                'loop' => (bool) ($base['loop'] ?? true),
                'muted' => (bool) ($base['muted'] ?? false),
            ],
            'audio' => [
                'volume' => $this->normalizeVolume($base['volume'] ?? 1),
                'loop' => (bool) ($base['loop'] ?? true),
                'muted' => (bool) ($base['muted'] ?? false),
            ],
            'text', 'overlay', 'countdown' => [
                'font_size' => max(12, (int) ($base['font_size'] ?? 52)),
                'font_color' => (string) ($base['font_color'] ?? '#ffffff'),
                'background_color' => $base['background_color'] ?? null,
                'background_opacity' => $this->normalizeRatio($base['background_opacity'] ?? 0),
                'align' => $base['align'] ?? 'left',
                'vertical_align' => $base['vertical_align'] ?? 'top',
                'padding' => max(0, (int) ($base['padding'] ?? 0)),
                'font_family' => (string) ($base['font_family'] ?? config('streaming.ffmpeg.font_family', 'Sans')),
                'line_spacing' => (int) ($base['line_spacing'] ?? 8),
                'opacity' => $this->normalizeRatio($base['opacity'] ?? 1),
                'ends_at' => $base['ends_at'] ?? null,
            ],
            default => $base,
        };
    }

    protected function defaultSettings(string $type): array
    {
        return match ($type) {
            'video', 'image' => [
                'fit' => 'cover',
                'opacity' => 1,
                'loop' => true,
                'muted' => false,
            ],
            'audio' => [
                'volume' => 1,
                'loop' => true,
                'muted' => false,
            ],
            'overlay' => [
                'font_size' => 44,
                'font_color' => '#ffffff',
                'background_color' => '#111827',
                'background_opacity' => 0.6,
                'align' => 'left',
                'vertical_align' => 'top',
                'padding' => 24,
                'font_family' => config('streaming.ffmpeg.font_family', 'Sans'),
                'line_spacing' => 8,
                'opacity' => 1,
            ],
            'countdown' => [
                'font_size' => 52,
                'font_color' => '#ffffff',
                'background_color' => '#111827',
                'background_opacity' => 0.7,
                'align' => 'center',
                'vertical_align' => 'middle',
                'padding' => 18,
                'font_family' => config('streaming.ffmpeg.font_family', 'Sans'),
                'line_spacing' => 6,
                'opacity' => 1,
            ],
            default => [
                'font_size' => 42,
                'font_color' => '#ffffff',
                'background_color' => null,
                'background_opacity' => 0,
                'align' => 'left',
                'vertical_align' => 'top',
                'padding' => 0,
                'font_family' => config('streaming.ffmpeg.font_family', 'Sans'),
                'line_spacing' => 8,
                'opacity' => 1,
            ],
        };
    }

    protected function validateVisualAndAudioSettings(string $type, array $settings): void
    {
        if (in_array($type, ['video', 'image'], true) && ! in_array($settings['fit'] ?? 'cover', ['contain', 'cover', 'stretch'], true)) {
            throw new \Exception('Visual layers require settings.fit to be contain, cover, or stretch');
        }

        if (in_array($type, ['video', 'image'], true) && (($settings['opacity'] ?? 1) < 0 || ($settings['opacity'] ?? 1) > 1)) {
            throw new \Exception('Visual layer opacity must be between 0 and 1');
        }

        if ($type === 'audio' && (($settings['volume'] ?? 1) < 0 || ($settings['volume'] ?? 1) > 4)) {
            throw new \Exception('Audio layer volume must be between 0 and 4');
        }
    }

    protected function validateTextSettings(array $settings): void
    {
        if (! in_array($settings['align'] ?? 'left', ['left', 'center', 'right'], true)) {
            throw new \Exception('Text alignment must be left, center, or right');
        }

        if (! in_array($settings['vertical_align'] ?? 'top', ['top', 'middle', 'bottom'], true)) {
            throw new \Exception('Vertical alignment must be top, middle, or bottom');
        }
    }

    protected function normalizeRatio(mixed $value): float
    {
        return max(0, min(1, (float) $value));
    }

    protected function normalizeVolume(mixed $value): float
    {
        return max(0, min(4, (float) $value));
    }
}
