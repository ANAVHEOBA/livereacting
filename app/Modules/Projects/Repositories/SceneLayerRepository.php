<?php

namespace App\Modules\Projects\Repositories;

use App\Models\SceneLayer;
use Illuminate\Database\Eloquent\Collection;

class SceneLayerRepository
{
    public function create(array $data): SceneLayer
    {
        return SceneLayer::create($data);
    }

    public function update(SceneLayer $layer, array $data): bool
    {
        return $layer->update($data);
    }

    public function delete(SceneLayer $layer): bool
    {
        return $layer->delete();
    }

    public function findByIdAndScene(int $layerId, int $sceneId): ?SceneLayer
    {
        return SceneLayer::with('file')
            ->where('id', $layerId)
            ->where('scene_id', $sceneId)
            ->first();
    }

    public function getByScene(int $sceneId): Collection
    {
        return SceneLayer::with('file')
            ->where('scene_id', $sceneId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getMaxSortOrder(int $sceneId): int
    {
        return (int) SceneLayer::where('scene_id', $sceneId)->max('sort_order');
    }
}
