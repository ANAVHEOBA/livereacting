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
        $settings = array_key_exists('settings', $data) ? ($data['settings'] ?? []) : ($layer?->settings ?? []);
        $position = array_key_exists('position', $data) ? ($data['position'] ?? []) : ($layer?->position ?? []);

        if (!$type) {
            throw new \Exception('Layer type is required');
        }

        $this->validateLayerState($userId, $type, $fileId, $content, $settings);

        return [
            'file_id' => $this->isFileBackedType($type) ? $fileId : null,
            'type' => $type,
            'name' => $data['name']
                ?? $layer?->name
                ?? $this->defaultLayerName($type, $fileId, $userId),
            'content' => $this->storesContent($type) ? $content : null,
            'is_visible' => $data['is_visible'] ?? $layer?->is_visible ?? true,
            'position' => !empty($position) ? $position : ($layer?->position ?? $this->defaultPosition($type)),
            'settings' => !empty($settings) ? $settings : ($layer?->settings ?? []),
        ];
    }

    protected function validateLayerState(int $userId, string $type, ?int $fileId, ?string $content, array $settings): void
    {
        if ($this->isFileBackedType($type)) {
            $file = $fileId ? $this->fileRepository->findByIdAndUser($fileId, $userId) : null;

            if (!$file) {
                throw new \Exception('Layer asset not found');
            }

            if (!$file->isReady()) {
                throw new \Exception('Layer asset must be ready before it can be used in a scene');
            }

            if ($file->type !== $type) {
                throw new \Exception("Layer asset type mismatch. Expected {$type} file");
            }

            return;
        }

        if (in_array($type, ['text', 'overlay'], true) && blank($content)) {
            throw new \Exception('Text and overlay layers require content');
        }

        if ($type === 'countdown' && blank($settings['ends_at'] ?? null)) {
            throw new \Exception('Countdown layers require settings.ends_at');
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

        return Str::headline($type) . ' Layer';
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
}
