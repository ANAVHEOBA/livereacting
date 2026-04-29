<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Requests\ApplySceneTemplateRequest;
use App\Modules\Projects\Requests\CreateSceneTemplateRequest;
use App\Modules\Projects\Resources\SceneResource;
use App\Modules\Projects\Resources\SceneTemplateResource;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Projects\Services\SceneService;
use App\Modules\Projects\Services\SceneTemplateService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SceneTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected SceneService $sceneService,
        protected SceneTemplateService $sceneTemplateService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success([
            'scene_templates' => SceneTemplateResource::collection(
                $this->sceneTemplateService->getTemplatesForUser($request->user()->id)
            ),
        ]);
    }

    public function store(CreateSceneTemplateRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $scene = $this->sceneService->getScene($project, $request->validated()['scene_id']);

        if (! $scene) {
            return $this->error('Scene not found', 404);
        }

        $template = $this->sceneTemplateService->createTemplateFromScene($scene, $request->user()->id, $request->validated());

        return $this->success(new SceneTemplateResource($template), 'Scene template created successfully', 201);
    }

    public function apply(ApplySceneTemplateRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $template = $this->sceneTemplateService->getTemplate($request->validated()['scene_template_id'], $request->user()->id);

        if (! $template) {
            return $this->error('Scene template not found', 404);
        }

        try {
            $scene = $this->sceneTemplateService->applyTemplate($project, $template, $request->user()->id, $request->validated());

            return $this->success(new SceneResource($scene), 'Scene template applied successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
