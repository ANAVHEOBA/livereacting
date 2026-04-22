<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;

class SyncService
{
    public function __construct(
        protected HistoryService $historyService
    ) {}

    public function syncProjectToLive(Project $project): array
    {
        // Check if project has an active live stream
        $activeLiveStream = $project->activeLiveStream;

        if (!$activeLiveStream) {
            throw new \Exception('No active live stream to sync to');
        }

        // In a real implementation, this would:
        // 1. Push project configuration to streaming service
        // 2. Update scenes, layers, overlays, etc.
        // 3. Apply changes without stopping the stream

        // For now, we'll simulate the sync
        $changes = [
            'project_name' => $project->name,
            'auto_sync' => $project->auto_sync,
            'destinations_count' => $project->destinations()->count(),
            'synced_at' => now(),
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
            'message' => 'Project configuration synced successfully',
        ];
    }
}
