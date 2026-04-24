<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Requests\CreateSceneRequest;
use App\Modules\Projects\Requests\ReorderScenesRequest;
use App\Modules\Projects\Requests\UpdateSceneRequest;
use App\Modules\Projects\Resources\SceneResource;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Projects\Services\SceneService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SceneController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected SceneService $sceneService
    ) {}

    public function index(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $scenes = $this->sceneService->getProjectScenes($project);

        return $this->success([
            'scenes' => SceneResource::collection($scenes),
            'total' => $scenes->count(),
        ]);
    }

    public function show(Request $request, int $projectId, int $sceneId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $scene = $this->sceneService->getScene($project, $sceneId);

        if (!$scene) {
            return $this->error('Scene not found', 404);
        }

        return $this->success(new SceneResource($scene));
    }

    public function store(CreateSceneRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $scene = $this->sceneService->createScene($project, $request->validated());

            return $this->success(new SceneResource($scene), 'Scene created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function update(UpdateSceneRequest $request, int $projectId, int $sceneId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $scene = $this->sceneService->getScene($project, $sceneId);

        if (!$scene) {
            return $this->error('Scene not found', 404);
        }

        try {
            $scene = $this->sceneService->updateScene($project, $scene, $request->validated());

            return $this->success(new SceneResource($scene), 'Scene updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function activate(Request $request, int $projectId, int $sceneId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $scene = $this->sceneService->getScene($project, $sceneId);

        if (!$scene) {
            return $this->error('Scene not found', 404);
        }

        try {
            $scene = $this->sceneService->activateScene($project, $scene);

            return $this->success(new SceneResource($scene), 'Scene activated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function reorder(ReorderScenesRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $scenes = $this->sceneService->reorderScenes($project, $request->validated()['scene_ids']);

            return $this->success([
                'scenes' => SceneResource::collection($scenes),
                'total' => $scenes->count(),
            ], 'Scenes reordered successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $projectId, int $sceneId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $scene = $this->sceneService->getScene($project, $sceneId);

        if (!$scene) {
            return $this->error('Scene not found', 404);
        }

        try {
            $this->sceneService->deleteScene($project, $scene);

            return $this->success(null, 'Scene deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
