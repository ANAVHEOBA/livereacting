<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Projects\Repositories\ProjectRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    public function __construct(
        protected ProjectRepository $projectRepository
    ) {}

    public function createProject(int $userId, array $data): Project
    {
        return $this->projectRepository->create([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'auto_sync' => $data['auto_sync'] ?? false,
            'status' => 'draft',
        ]);
    }

    public function updateProject(Project $project, array $data): Project
    {
        if (!$project->canBeUpdated()) {
            throw new \Exception('Cannot update project while it is live');
        }

        $this->projectRepository->update($project, [
            'name' => $data['name'] ?? $project->name,
            'description' => $data['description'] ?? $project->description,
            'thumbnail' => $data['thumbnail'] ?? $project->thumbnail,
            'auto_sync' => $data['auto_sync'] ?? $project->auto_sync,
        ]);

        return $project->fresh();
    }

    public function deleteProject(Project $project): bool
    {
        if (!$project->canBeDeleted()) {
            throw new \Exception('Cannot delete project while it is live or scheduled');
        }

        return $this->projectRepository->delete($project);
    }

    public function getProject(int $projectId, int $userId): ?Project
    {
        return $this->projectRepository->findByIdAndUser($projectId, $userId);
    }

    public function getUserProjects(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->projectRepository->getAllByUser($userId, $filters, $perPage);
    }
}
