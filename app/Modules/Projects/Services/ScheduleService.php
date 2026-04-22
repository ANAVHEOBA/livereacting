<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Modules\Projects\Repositories\ProjectRepository;
use App\Modules\Projects\Repositories\ScheduleRepository;

class ScheduleService
{
    public function __construct(
        protected ScheduleRepository $scheduleRepository,
        protected ProjectRepository $projectRepository,
        protected LiveStreamService $liveStreamService
    ) {}

    public function scheduleStream(Project $project, array $data): ProjectSchedule
    {
        // Validate project can be scheduled
        $validation = $this->liveStreamService->validateProject($project);
        if (!$validation['valid']) {
            throw new \Exception(implode(', ', $validation['errors']));
        }

        // Check if start time is in the future
        $startAt = \Carbon\Carbon::parse($data['start_at']);
        if ($startAt->isPast()) {
            throw new \Exception('Schedule start time must be in the future');
        }

        // Check for scheduling conflicts
        $hasConflict = $this->scheduleRepository->hasConflict(
            $project->id,
            $startAt,
            $data['duration'] ?? null
        );

        if ($hasConflict) {
            throw new \Exception('Scheduling conflict: Another stream is scheduled at this time');
        }

        // Create schedule
        $schedule = $this->scheduleRepository->create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'start_at' => $startAt,
            'duration' => $data['duration'] ?? null,
            'format' => $data['format'] ?? '720p',
            'status' => 'scheduled',
        ]);

        // Update project status to scheduled
        $this->projectRepository->update($project, [
            'status' => 'scheduled',
        ]);

        return $schedule;
    }

    public function cancelSchedules(Project $project): int
    {
        $schedules = $this->scheduleRepository->getActiveSchedulesByProject($project->id);

        if ($schedules->isEmpty()) {
            throw new \Exception('No active schedules found for this project');
        }

        $count = 0;
        foreach ($schedules as $schedule) {
            if ($schedule->canBeCancelled()) {
                $this->scheduleRepository->update($schedule, [
                    'status' => 'cancelled',
                ]);
                $count++;
            }
        }

        // Update project status back to draft if no more active schedules
        if (!$project->hasActiveSchedules() && !$project->hasActiveLiveStream()) {
            $this->projectRepository->update($project, [
                'status' => 'draft',
            ]);
        }

        return $count;
    }

    public function getProjectSchedules(Project $project)
    {
        return $this->scheduleRepository->getByProject($project->id);
    }
}
