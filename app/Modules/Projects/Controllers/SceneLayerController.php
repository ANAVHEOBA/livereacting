<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Requests\CreateSceneLayerRequest;
use App\Modules\Projects\Requests\ReorderSceneLayersRequest;
use App\Modules\Projects\Requests\UpdateSceneLayerRequest;
use App\Modules\Projects\Resources\SceneLayerResource;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Projects\Services\SceneLayerService;
use App\Modules\Projects\Services\SceneService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SceneLayerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected SceneService $sceneService,
        protected SceneLayerService $sceneLayerService
    ) {}

    public function index(Request $request, int $projectId, int $sceneId): JsonResponse
    {
        [$project, $scene] = $this->resolveProjectAndScene($request, $projectId, $sceneId);

        if (!$project || !$scene) {
            return !$project
                ? $this->error('Project not found', 404)
                : $this->error('Scene not found', 404);
        }

        $layers = $this->sceneLayerService->getSceneLayers($scene);

        return $this->success([
            'layers' => SceneLayerResource::collection($layers),
            'total' => $layers->count(),
        ]);
    }

    public function store(CreateSceneLayerRequest $request, int $projectId, int $sceneId): JsonResponse
    {
        [$project, $scene] = $this->resolveProjectAndScene($request, $projectId, $sceneId);

        if (!$project || !$scene) {
            return !$project
                ? $this->error('Project not found', 404)
                : $this->error('Scene not found', 404);
        }

        try {
            $layer = $this->sceneLayerService->createLayer(
                $project,
                $scene,
                $request->user()->id,
                $request->validated()
            );

            return $this->success(new SceneLayerResource($layer), 'Scene layer created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function update(UpdateSceneLayerRequest $request, int $projectId, int $sceneId, int $layerId): JsonResponse
    {
        [$project, $scene] = $this->resolveProjectAndScene($request, $projectId, $sceneId);

        if (!$project || !$scene) {
            return !$project
                ? $this->error('Project not found', 404)
                : $this->error('Scene not found', 404);
        }

        $layer = $this->sceneLayerService->getLayer($scene, $layerId);

        if (!$layer) {
            return $this->error('Layer not found', 404);
        }

        try {
            $layer = $this->sceneLayerService->updateLayer(
                $project,
                $scene,
                $layer,
                $request->user()->id,
                $request->validated()
            );

            return $this->success(new SceneLayerResource($layer), 'Scene layer updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function reorder(ReorderSceneLayersRequest $request, int $projectId, int $sceneId): JsonResponse
    {
        [$project, $scene] = $this->resolveProjectAndScene($request, $projectId, $sceneId);

        if (!$project || !$scene) {
            return !$project
                ? $this->error('Project not found', 404)
                : $this->error('Scene not found', 404);
        }

        try {
            $layers = $this->sceneLayerService->reorderLayers($project, $scene, $request->validated()['layer_ids']);

            return $this->success([
                'layers' => SceneLayerResource::collection($layers),
                'total' => $layers->count(),
            ], 'Scene layers reordered successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $projectId, int $sceneId, int $layerId): JsonResponse
    {
        [$project, $scene] = $this->resolveProjectAndScene($request, $projectId, $sceneId);

        if (!$project || !$scene) {
            return !$project
                ? $this->error('Project not found', 404)
                : $this->error('Scene not found', 404);
        }

        $layer = $this->sceneLayerService->getLayer($scene, $layerId);

        if (!$layer) {
            return $this->error('Layer not found', 404);
        }

        try {
            $this->sceneLayerService->deleteLayer($project, $scene, $layer);

            return $this->success(null, 'Scene layer deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    protected function resolveProjectAndScene(Request $request, int $projectId, int $sceneId): array
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);
        $scene = $project ? $this->sceneService->getScene($project, $sceneId) : null;

        return [$project, $scene];
    }
}
