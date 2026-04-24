<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\Scene;
use App\Modules\Projects\Repositories\ProjectRepository;
use App\Modules\Projects\Repositories\SceneRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SceneService
{
    public function __construct(
        protected SceneRepository $sceneRepository,
        protected ProjectRepository $projectRepository,
        protected HistoryService $historyService
    ) {}

    public function createDefaultScene(Project $project): Scene
    {
        if ($project->scenes()->exists()) {
            return $project->scenes()->orderBy('sort_order')->first();
        }

        return DB::transaction(function () use ($project) {
            $scene = $this->sceneRepository->create([
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'name' => 'Scene 1',
                'transition' => 'cut',
                'sort_order' => 1,
                'settings' => [],
            ]);

            $this->projectRepository->update($project, [
                'active_scene_id' => $scene->id,
            ]);

            $this->historyService->logAction(
                $project,
                'scene_created',
                'Default scene created',
                [
                    'scene_id' => $scene->id,
                    'scene_name' => $scene->name,
                ]
            );

            return $scene->fresh('project');
        });
    }

    public function getProjectScenes(Project $project): Collection
    {
        return $this->sceneRepository->getByProject($project->id);
    }

    public function getScene(Project $project, int $sceneId): ?Scene
    {
        return $this->sceneRepository->findByIdAndProject($sceneId, $project->id);
    }

    public function createScene(Project $project, array $data): Scene
    {
        return DB::transaction(function () use ($project, $data) {
            $scene = $this->sceneRepository->create([
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'name' => $data['name'],
                'transition' => $data['transition'] ?? 'cut',
                'duration' => $data['duration'] ?? null,
                'sort_order' => $this->sceneRepository->getMaxSortOrder($project->id) + 1,
                'settings' => $data['settings'] ?? [],
            ]);

            if (!$project->active_scene_id) {
                $this->projectRepository->update($project, [
                    'active_scene_id' => $scene->id,
                ]);
            }

            $this->historyService->logAction(
                $project,
                'scene_created',
                'Scene created',
                [
                    'scene_id' => $scene->id,
                    'scene_name' => $scene->name,
                ]
            );

            return $scene->fresh(['project', 'layers.file']);
        });
    }

    public function updateScene(Project $project, Scene $scene, array $data): Scene
    {
        $this->sceneRepository->update($scene, [
            'name' => $data['name'] ?? $scene->name,
            'transition' => $data['transition'] ?? $scene->transition,
            'duration' => $data['duration'] ?? $scene->duration,
            'settings' => $data['settings'] ?? $scene->settings,
        ]);

        $updatedScene = $scene->fresh(['project', 'layers.file']);

        $this->historyService->logAction(
            $project,
            'scene_updated',
            'Scene updated',
            [
                'scene_id' => $updatedScene->id,
                'fields' => array_keys($data),
            ]
        );

        return $updatedScene;
    }

    public function activateScene(Project $project, Scene $scene): Scene
    {
        $this->projectRepository->update($project, [
            'active_scene_id' => $scene->id,
        ]);

        $this->historyService->logAction(
            $project,
            'scene_activated',
            'Scene activated',
            [
                'scene_id' => $scene->id,
                'scene_name' => $scene->name,
            ]
        );

        return $scene->fresh(['project', 'layers.file']);
    }

    public function reorderScenes(Project $project, array $sceneIds): Collection
    {
        $scenes = $this->sceneRepository->getByProject($project->id);
        $currentIds = $scenes->pluck('id')->sort()->values()->all();
        $requestedIds = collect($sceneIds)->sort()->values()->all();

        if ($currentIds !== $requestedIds) {
            throw new \Exception('Scene reorder payload must contain every project scene exactly once');
        }

        DB::transaction(function () use ($sceneIds, $project) {
            foreach (array_values($sceneIds) as $index => $sceneId) {
                $scene = $this->sceneRepository->findByIdAndProject($sceneId, $project->id);

                $this->sceneRepository->update($scene, [
                    'sort_order' => $index + 1,
                ]);
            }
        });

        $this->historyService->logAction(
            $project,
            'scenes_reordered',
            'Scenes reordered'
        );

        return $this->sceneRepository->getByProject($project->id);
    }

    public function deleteScene(Project $project, Scene $scene): void
    {
        $scenes = $this->sceneRepository->getByProject($project->id);

        if ($scenes->count() <= 1) {
            throw new \Exception('A project must keep at least one scene');
        }

        DB::transaction(function () use ($project, $scene) {
            $deletedSceneId = $scene->id;

            $this->sceneRepository->delete($scene);

            $remainingScenes = $this->sceneRepository->getByProject($project->id)->values();

            foreach ($remainingScenes as $index => $remainingScene) {
                if ($remainingScene->sort_order !== $index + 1) {
                    $this->sceneRepository->update($remainingScene, [
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            if ((int) $project->active_scene_id === $deletedSceneId) {
                $replacementScene = $remainingScenes->first();

                $this->projectRepository->update($project, [
                    'active_scene_id' => $replacementScene?->id,
                ]);
            }
        });

        $this->historyService->logAction(
            $project,
            'scene_deleted',
            'Scene deleted',
            [
                'scene_id' => $scene->id,
                'scene_name' => $scene->name,
            ]
        );
    }
}
