<?php

namespace App\Modules\Projects\Repositories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository
{
    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function findById(int $id): ?Project
    {
        return Project::find($id);
    }

    public function findByIdAndUser(int $id, int $userId): ?Project
    {
        return Project::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function update(Project $project, array $data): bool
    {
        return $project->update($data);
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }

    public function getAllByUser(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Project::where('user_id', $userId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function countByUser(int $userId): int
    {
        return Project::where('user_id', $userId)->count();
    }
}
