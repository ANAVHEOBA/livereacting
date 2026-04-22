<?php

namespace App\Modules\Projects\Repositories;

use App\Models\ProjectHistory;
use Illuminate\Pagination\LengthAwarePaginator;

class HistoryRepository
{
    public function create(array $data): ProjectHistory
    {
        return ProjectHistory::create($data);
    }

    public function getByProject(int $projectId, int $perPage = 20): LengthAwarePaginator
    {
        return ProjectHistory::where('project_id', $projectId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
