<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Requests\CreateProjectRequest;
use App\Modules\Projects\Requests\UpdateProjectRequest;
use App\Modules\Projects\Resources\ProjectResource;
use App\Modules\Projects\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        $perPage = $request->query('per_page', 20);

        $projects = $this->projectService->getUserProjects(
            $request->user()->id,
            $filters,
            $perPage
        );

        return $this->success([
            'projects' => ProjectResource::collection($projects->items()),
            'total' => $projects->total(),
            'per_page' => $projects->perPage(),
            'current_page' => $projects->currentPage(),
            'last_page' => $projects->lastPage(),
        ]);
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject(
            $request->user()->id,
            $request->validated()
        );

        return $this->success(
            new ProjectResource($project),
            'Project created successfully',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = $this->projectService->getProject($id, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $includes = collect(explode(',', (string) $request->query('include')))
            ->map(fn (string $include) => trim($include))
            ->filter()
            ->values();

        $relations = [];

        if ($includes->contains('destinations')) {
            $relations[] = 'destinations';
        }

        if ($includes->contains('scenes')) {
            $relations[] = 'scenes.layers.file';
        }

        if ($includes->contains('activeScene')) {
            $relations[] = 'activeScene.layers.file';
        }

        if ($includes->contains('interactiveElements')) {
            $relations[] = 'interactiveElements.responses';
        }

        if ($includes->contains('guestRoom')) {
            $relations[] = 'guestRoom.invites';
            $relations[] = 'guestRoom.sessions';
        }

        if (! empty($relations)) {
            $project->load($relations);
        }

        return $this->success(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = $this->projectService->getProject($id, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        try {
            $updatedProject = $this->projectService->updateProject($project, $request->validated());

            return $this->success(
                new ProjectResource($updatedProject),
                'Project updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $project = $this->projectService->getProject($id, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        try {
            $this->projectService->deleteProject($project);

            return $this->success(null, 'Project deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
