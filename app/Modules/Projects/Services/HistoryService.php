<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Projects\Repositories\HistoryRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class HistoryService
{
    public function __construct(
        protected HistoryRepository $historyRepository
    ) {}

    public function logAction(Project $project, string $action, string $description, ?array $metadata = null): void
    {
        $this->historyRepository->create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public function getProjectHistory(Project $project, int $perPage = 20): LengthAwarePaginator
    {
        return $this->historyRepository->getByProject($project->id, $perPage);
    }
}
