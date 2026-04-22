<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Destinations\Resources\DestinationResource;
use App\Modules\Projects\Requests\LinkDestinationRequest;
use App\Modules\Projects\Services\ProjectDestinationService;
use App\Modules\Projects\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectDestinationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected ProjectDestinationService $projectDestinationService
    ) {}

    public function index(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $destinations = $project->destinations;

        return $this->success([
            'destinations' => DestinationResource::collection($destinations),
            'total' => $destinations->count(),
        ]);
    }

    public function store(LinkDestinationRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $this->projectDestinationService->linkDestination(
                $project,
                $request->validated()['destination_id'],
                $request->user()->id
            );

            return $this->success(null, 'Destination linked successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $projectId, int $destinationId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $this->projectDestinationService->unlinkDestination($project, $destinationId);

            return $this->success(null, 'Destination unlinked successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
