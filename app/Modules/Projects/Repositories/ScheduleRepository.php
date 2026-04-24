<?php

namespace App\Modules\Projects\Repositories;

use App\Models\ProjectSchedule;
use Carbon\Carbon;
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

    public function getDueSchedules(): Collection
    {
        return ProjectSchedule::with('project.destinations')
            ->where('status', 'scheduled')
            ->where('start_at', '<=', now())
            ->orderBy('start_at')
            ->get();
    }

    public function hasConflict(int $projectId, Carbon $startAt, ?int $duration): bool
    {
        $schedules = ProjectSchedule::where('project_id', $projectId)
            ->where('status', 'scheduled')
            ->orderBy('start_at')
            ->get();

        foreach ($schedules as $schedule) {
            if ($this->schedulesOverlap($schedule, $startAt, $duration)) {
                return true;
            }
        }

        return false;
    }

    protected function schedulesOverlap(ProjectSchedule $schedule, Carbon $startAt, ?int $duration): bool
    {
        $existingStart = $schedule->start_at->copy();
        $existingEnd = $schedule->duration
            ? $schedule->start_at->copy()->addSeconds($schedule->duration)
            : null;
        $newEnd = $duration ? $startAt->copy()->addSeconds($duration) : null;

        if ($existingEnd === null && $newEnd === null) {
            return true;
        }

        if ($existingEnd === null) {
            return $existingStart->lessThanOrEqualTo($startAt);
        }

        if ($newEnd === null) {
            return $startAt->lessThan($existingEnd);
        }

        return $existingStart->lessThan($newEnd) && $startAt->lessThan($existingEnd);
    }
}
