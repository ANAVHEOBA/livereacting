<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Resources\HistoryResource;
use App\Modules\Projects\Services\AnalyticsService;
use App\Modules\Projects\Services\HistoryService;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Projects\Services\SyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected SyncService $syncService,
        protected HistoryService $historyService,
        protected AnalyticsService $analyticsService
    ) {}

    public function sync(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        try {
            $result = $this->syncService->syncProjectToLive($project);

            return $this->success($result, 'Project synced successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function history(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $perPage = $request->query('per_page', 20);
        $history = $this->historyService->getProjectHistory($project, $perPage);

        return $this->success([
            'history' => HistoryResource::collection($history->items()),
            'total' => $history->total(),
            'per_page' => $history->perPage(),
            'current_page' => $history->currentPage(),
            'last_page' => $history->lastPage(),
        ]);
    }

    public function analytics(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $analytics = $this->analyticsService->getProjectAnalytics($project);

        return $this->success($analytics);
    }
}
