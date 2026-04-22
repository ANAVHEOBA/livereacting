<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Requests\StartLiveStreamRequest;
use App\Modules\Projects\Resources\LiveStreamResource;
use App\Modules\Projects\Services\LiveStreamService;
use App\Modules\Projects\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveStreamController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected LiveStreamService $liveStreamService
    ) {}

    public function validate(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $validation = $this->liveStreamService->validateProject($project);

        if (!$validation['valid']) {
            return $this->error('Validation failed', 400, $validation['errors']);
        }

        return $this->success([
            'valid' => true,
            'message' => 'Project is ready to go live',
        ]);
    }

    public function start(StartLiveStreamRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $liveStream = $this->liveStreamService->startLiveStream(
                $project,
                $request->validated()
            );

            return $this->success(
                new LiveStreamResource($liveStream),
                'Live stream started successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function stop(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $this->liveStreamService->stopLiveStream($project);

            return $this->success(null, 'Live stream stopped successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
