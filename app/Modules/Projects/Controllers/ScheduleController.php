<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Requests\ScheduleStreamRequest;
use App\Modules\Projects\Resources\ScheduleResource;
use App\Modules\Projects\Services\ScheduleService;
use App\Modules\Projects\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected ScheduleService $scheduleService
    ) {}

    public function index(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $schedules = $this->scheduleService->getProjectSchedules($project);

        return $this->success([
            'schedules' => ScheduleResource::collection($schedules),
            'total' => $schedules->count(),
        ]);
    }

    public function store(ScheduleStreamRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $schedule = $this->scheduleService->scheduleStream(
                $project,
                $request->validated()
            );

            return $this->success(
                new ScheduleResource($schedule),
                'Stream scheduled successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $cancelled = $this->scheduleService->cancelSchedules($project);

            return $this->success([
                'cancelled_count' => $cancelled,
            ], 'Schedules cancelled successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
