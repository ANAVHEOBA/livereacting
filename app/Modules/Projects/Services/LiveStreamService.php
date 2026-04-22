<?php

namespace App\Modules\Projects\Services;

use App\Models\LiveStream;
use App\Models\Project;
use App\Modules\Projects\Repositories\LiveStreamRepository;
use App\Modules\Projects\Repositories\ProjectRepository;

class LiveStreamService
{
    public function __construct(
        protected LiveStreamRepository $liveStreamRepository,
        protected ProjectRepository $projectRepository,
        protected HistoryService $historyService
    ) {}

    public function validateProject(Project $project): array
    {
        $errors = [];

        // Check if project has destinations
        if (!$project->destinations()->exists()) {
            $errors[] = 'Project has no streaming destinations';
        }

        // Check if destinations are valid
        $invalidDestinations = $project->destinations()
            ->where('is_valid', false)
            ->get();

        if ($invalidDestinations->isNotEmpty()) {
            $errors[] = 'Some destinations have invalid or expired tokens: ' . 
                $invalidDestinations->pluck('name')->join(', ');
        }

        // Check if project already has an active stream
        if ($project->hasActiveLiveStream()) {
            $errors[] = 'Project already has an active or preparing stream';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function startLiveStream(Project $project, array $data): LiveStream
    {
        // Validate project
        $validation = $this->validateProject($project);
        if (!$validation['valid']) {
            throw new \Exception(implode(', ', $validation['errors']));
        }

        // Create live stream
        $liveStream = $this->liveStreamRepository->create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'status' => 'preparing',
            'format' => $data['format'] ?? '720p',
            'duration' => $data['duration'] ?? null,
        ]);

        // Update project status and active_live_id
        $this->projectRepository->update($project, [
            'status' => 'live',
            'active_live_id' => $liveStream->id,
        ]);

        // Simulate stream starting (in real app, this would call streaming service)
        $this->liveStreamRepository->update($liveStream, [
            'status' => 'live',
            'started_at' => now(),
        ]);

        // Log history
        $this->historyService->logAction(
            $project,
            'live_started',
            'Live stream started',
            [
                'live_stream_id' => $liveStream->id,
                'format' => $liveStream->format,
                'duration' => $liveStream->duration,
            ]
        );

        return $liveStream->fresh();
    }

    public function stopLiveStream(Project $project): bool
    {
        $liveStream = $this->liveStreamRepository->findActiveByProject($project->id);

        if (!$liveStream) {
            throw new \Exception('No active live stream found for this project');
        }

        if (!$liveStream->canBeStopped()) {
            throw new \Exception('Live stream cannot be stopped in current state');
        }

        // Update live stream status
        $this->liveStreamRepository->update($liveStream, [
            'status' => 'stopped',
            'ended_at' => now(),
        ]);

        // Update project status
        $this->projectRepository->update($project, [
            'status' => 'completed',
            'active_live_id' => null,
        ]);

        // Log history
        $this->historyService->logAction(
            $project,
            'live_stopped',
            'Live stream stopped',
            [
                'live_stream_id' => $liveStream->id,
                'duration_seconds' => $liveStream->started_at 
                    ? now()->diffInSeconds($liveStream->started_at) 
                    : null,
            ]
        );

        return true;
    }

    public function getActiveLiveStream(Project $project): ?LiveStream
    {
        return $this->liveStreamRepository->findActiveByProject($project->id);
    }
}
