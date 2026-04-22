<?php

namespace App\Modules\Projects\Repositories;

use App\Models\ProjectSchedule;
use Illuminate\Database\Eloquent\Collection;

class ScheduleRepository
{
    public function create(array $data): ProjectSchedule
    {
        return ProjectSchedule::create($data);
    }

    public function findById(int $id): ?ProjectSchedule
    {
        return ProjectSchedule::find($id);
    }

    public function update(ProjectSchedule $schedule, array $data): bool
    {
        return $schedule->update($data);
    }

    public function getByProject(int $projectId): Collection
    {
        return ProjectSchedule::where('project_id', $projectId)
            ->orderBy('start_at', 'desc')
            ->get();
    }

    public function getActiveSchedulesByProject(int $projectId): Collection
    {
        return ProjectSchedule::where('project_id', $projectId)
            ->where('status', 'scheduled')
            ->where('start_at', '>', now())
            ->get();
    }

    public function hasConflict(int $projectId, \Carbon\Carbon $startAt, ?int $duration): bool
    {
        $query = ProjectSchedule::where('project_id', $projectId)
            ->where('status', 'scheduled');

        if ($duration) {
            // Check if new schedule overlaps with existing schedules
            $endAt = $startAt->copy()->addSeconds($duration);
            
            $query->where(function ($q) use ($startAt, $endAt) {
                // New schedule starts during existing schedule
                $q->where(function ($subQ) use ($startAt) {
                    $subQ->where('start_at', '<=', $startAt)
                        ->whereRaw('DATE_ADD(start_at, INTERVAL duration SECOND) > ?', [$startAt]);
                })
                // New schedule ends during existing schedule
                ->orWhere(function ($subQ) use ($endAt) {
                    $subQ->where('start_at', '<', $endAt)
                        ->whereRaw('DATE_ADD(start_at, INTERVAL duration SECOND) >= ?', [$endAt]);
                })
                // New schedule completely contains existing schedule
                ->orWhere(function ($subQ) use ($startAt, $endAt) {
                    $subQ->where('start_at', '>=', $startAt)
                        ->whereRaw('DATE_ADD(start_at, INTERVAL duration SECOND) <= ?', [$endAt]);
                });
            });
        } else {
            // If no duration, just check if there's any schedule at the same time
            $query->where('start_at', $startAt);
        }

        return $query->exists();
    }
}
