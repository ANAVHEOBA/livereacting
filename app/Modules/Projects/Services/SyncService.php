<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Projects\Repositories\LiveStreamRepository;

class SyncService
{
    public function __construct(
        protected HistoryService $historyService,
        protected LiveStreamRepository $liveStreamRepository,
        protected StudioPayloadService $studioPayloadService,
        protected StreamWorkerService $streamWorkerService
    ) {}

    public function syncProjectToLive(Project $project): array
    {
        // Check if project has an active live stream
        $activeLiveStream = $project->activeLiveStream;

        if (! $activeLiveStream) {
            throw new \Exception('No active live stream to sync to');
        }

        $validation = $this->studioPayloadService->validateProjectStudio($project);

        if (! $validation['valid']) {
            throw new \Exception(implode(', ', $validation['errors']));
        }

        $studioPayload = $this->studioPayloadService->buildStudioPayload($project);
        $metadata = $activeLiveStream->metadata ?? [];
        $metadata['studio_snapshot'] = $studioPayload;
        $metadata['sync_count'] = ($metadata['sync_count'] ?? 0) + 1;
        $metadata['last_synced_at'] = now()->toIso8601String();

        $this->liveStreamRepository->update($activeLiveStream, [
            'metadata' => $metadata,
        ]);

        $workerSync = $this->streamWorkerService->sync($activeLiveStream->fresh());

        if (($workerSync['restart_required'] ?? false) === true) {
            $worker = $this->streamWorkerService->restart($activeLiveStream->fresh());
            $metadata = array_merge($metadata, ['worker' => $worker]);

            $this->liveStreamRepository->update($activeLiveStream->fresh(), [
                'metadata' => $metadata,
            ]);
        } elseif (($workerSync['worker'] ?? null) !== null) {
            $metadata = array_merge($metadata, ['worker' => $workerSync['worker']]);

            $this->liveStreamRepository->update($activeLiveStream->fresh(), [
                'metadata' => $metadata,
            ]);
        }

        $changes = [
            'project_name' => $project->name,
            'auto_sync' => $project->auto_sync,
            'active_scene_id' => $project->active_scene_id,
            'scene_count' => $studioPayload['scene_count'],
            'layer_count' => $studioPayload['layer_count'],
            'destinations_count' => count($studioPayload['destinations']),
            'synced_at' => $metadata['last_synced_at'],
            'worker_restart_required' => $workerSync['restart_required'] ?? false,
        ];

        // Log the sync action
        $this->historyService->logAction(
            $project,
            'synced',
            'Project configuration synced to live stream',
            $changes
        );

        return [
            'synced' => true,
            'live_stream_id' => $activeLiveStream->id,
            'changes' => $changes,
            'studio' => $studioPayload,
            'message' => 'Project configuration synced successfully',
        ];
    }
}
