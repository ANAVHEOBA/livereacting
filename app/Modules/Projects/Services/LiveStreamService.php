<?php

namespace App\Modules\Projects\Services;

use App\Models\LiveStream;
use App\Models\Project;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Projects\Repositories\LiveStreamRepository;
use App\Modules\Projects\Repositories\ProjectRepository;

class LiveStreamService
{
    public function __construct(
        protected LiveStreamRepository $liveStreamRepository,
        protected ProjectRepository $projectRepository,
        protected HistoryService $historyService,
        protected StudioPayloadService $studioPayloadService,
        protected BillingService $billingService,
        protected LiveDestinationProvisioningService $liveDestinationProvisioningService,
        protected StreamWorkerService $streamWorkerService
    ) {}

    public function validateProject(Project $project): array
    {
        $errors = [];

        // Check if project has destinations
        if (! $project->destinations()->exists()) {
            $errors[] = 'Project has no streaming destinations';
        }

        // Check if destinations are valid
        $invalidDestinations = $project->destinations()
            ->where('is_valid', false)
            ->get();

        if ($invalidDestinations->isNotEmpty()) {
            $errors[] = 'Some destinations have invalid or expired tokens: '.
                $invalidDestinations->pluck('name')->join(', ');
        }

        // Check if project already has an active stream
        if ($project->hasActiveLiveStream()) {
            $errors[] = 'Project already has an active or preparing stream';
        }

        $studioValidation = $this->studioPayloadService->validateProjectStudio($project);

        if (! $studioValidation['valid']) {
            $errors = array_merge($errors, $studioValidation['errors']);
        }

        try {
            $this->billingService->assertProjectCanGoLive($project->loadMissing('user'), $project->activeLiveStream?->duration);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
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
        if (! $validation['valid']) {
            throw new \Exception(implode(', ', $validation['errors']));
        }

        $studioPayload = $this->studioPayloadService->buildStudioPayload($project);
        $this->billingService->assertProjectCanGoLive($project->loadMissing('user'), $data['duration'] ?? null);

        // Create live stream
        $liveStream = $this->liveStreamRepository->create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'status' => 'preparing',
            'format' => $data['format'] ?? '720p',
            'duration' => $data['duration'] ?? null,
            'metadata' => [
                'started_from_scene_id' => $project->active_scene_id,
                'studio_snapshot' => $studioPayload,
                'sync_count' => 0,
                'last_synced_at' => null,
            ],
        ]);

        try {
            $destinationRuntime = $this->liveDestinationProvisioningService->provision(
                $project,
                $liveStream,
                $data
            );

            $this->liveStreamRepository->update($liveStream, [
                'metadata' => array_merge($liveStream->metadata ?? [], [
                    'destination_sessions' => $destinationRuntime['sessions'],
                    'egress' => $destinationRuntime['egress'],
                ]),
            ]);

            $worker = $this->streamWorkerService->start($liveStream->fresh());

            // Update project status and active_live_id
            $this->projectRepository->update($project, [
                'status' => 'live',
                'active_live_id' => $liveStream->id,
            ]);

            $this->liveStreamRepository->update($liveStream, [
                'status' => 'live',
                'started_at' => now(),
                'metadata' => array_merge($liveStream->fresh()->metadata ?? [], [
                    'worker' => $worker,
                ]),
            ]);
        } catch (\Throwable $e) {
            $this->liveStreamRepository->update($liveStream, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Log history
        $this->historyService->logAction(
            $project,
            'live_started',
            'Live stream started',
            [
                'live_stream_id' => $liveStream->id,
                'format' => $liveStream->format,
                'duration' => $liveStream->duration,
                'active_scene_id' => $project->active_scene_id,
                'scene_count' => $studioPayload['scene_count'],
                'layer_count' => $studioPayload['layer_count'],
            ]
        );

        $this->billingService->consumeCredits(
            $project->user,
            10,
            'Live stream started',
            'live_stream',
            $liveStream->id,
            [
                'format' => $liveStream->format,
                'destination_count' => count($destinationRuntime['sessions']),
            ]
        );

        return $liveStream->fresh();
    }

    public function stopLiveStream(Project $project): bool
    {
        $liveStream = $this->liveStreamRepository->findActiveByProject($project->id);

        if (! $liveStream) {
            throw new \Exception('No active live stream found for this project');
        }

        if (! $liveStream->canBeStopped()) {
            throw new \Exception('Live stream cannot be stopped in current state');
        }

        $worker = $this->streamWorkerService->stop($liveStream);

        $destinationFinalization = $this->liveDestinationProvisioningService->finalize($liveStream);
        $hasProvisioningErrors = collect($destinationFinalization)
            ->contains(fn (array $result): bool => ($result['status'] ?? null) === 'error');

        $metadata = array_merge($liveStream->metadata ?? [], [
            'worker' => $worker,
            'destination_finalization' => $destinationFinalization,
            'stopped_at' => now()->toIso8601String(),
        ]);

        // Update live stream status
        $this->liveStreamRepository->update($liveStream, [
            'status' => $hasProvisioningErrors ? 'failed' : 'stopped',
            'ended_at' => now(),
            'metadata' => $metadata,
            'error_message' => $hasProvisioningErrors
                ? 'One or more destination sessions failed to finalize'
                : null,
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
                'destination_finalization' => $destinationFinalization,
            ]
        );

        return true;
    }

    public function getActiveLiveStream(Project $project): ?LiveStream
    {
        return $this->liveStreamRepository->findActiveByProject($project->id);
    }
}
