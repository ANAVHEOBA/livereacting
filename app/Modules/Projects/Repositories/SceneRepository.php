<?php

namespace App\Modules\Projects\Repositories;

use App\Models\Scene;
use Illuminate\Database\Eloquent\Collection;

class SceneRepository
{
    public function create(array $data): Scene
    {
        return Scene::create($data);
    }

    public function update(Scene $scene, array $data): bool
    {
        return $scene->update($data);
    }

    public function delete(Scene $scene): bool
    {
        return $scene->delete();
    }

    public function findByIdAndProject(int $sceneId, int $projectId): ?Scene
    {
        return Scene::with(['project', 'layers.file'])
            ->where('id', $sceneId)
            ->where('project_id', $projectId)
            ->first();
    }

    public function getByProject(int $projectId): Collection
    {
        return Scene::with(['project', 'layers.file'])
            ->where('project_id', $projectId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getMaxSortOrder(int $projectId): int
    {
        return (int) Scene::where('project_id', $projectId)->max('sort_order');
    }
}
