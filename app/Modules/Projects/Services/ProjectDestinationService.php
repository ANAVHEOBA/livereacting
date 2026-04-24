<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Destinations\Repositories\DestinationRepository;

class ProjectDestinationService
{
    public function __construct(
        protected DestinationRepository $destinationRepository,
        protected HistoryService $historyService
    ) {}

    public function linkDestination(Project $project, int $destinationId, int $userId): void
    {
        // Verify destination belongs to user
        $destination = $this->destinationRepository->findByIdAndUser($destinationId, $userId);

        if (!$destination) {
            throw new \Exception('Destination not found');
        }

        // Check if destination needs reconnection
        if ($destination->needsReconnection()) {
            throw new \Exception('Destination needs reconnection. Please reconnect in settings.');
        }

        // Check if already linked
        if ($project->destinations()->where('streaming_destination_id', $destinationId)->exists()) {
            throw new \Exception('Destination already linked to this project');
        }

        // Link destination
        $project->destinations()->attach($destinationId);

        $this->historyService->logAction(
            $project,
            'destination_linked',
            'Destination linked to project',
            [
                'destination_id' => $destination->id,
                'destination_name' => $destination->name,
                'destination_type' => $destination->type,
            ]
        );
    }

    public function unlinkDestination(Project $project, int $destinationId): void
    {
        $destination = $project->destinations()
            ->where('streaming_destination_id', $destinationId)
            ->first();

        // Check if destination is linked
        if (!$destination) {
            throw new \Exception('Destination not linked to this project');
        }

        // Unlink destination
        $project->destinations()->detach($destinationId);

        $this->historyService->logAction(
            $project,
            'destination_unlinked',
            'Destination unlinked from project',
            [
                'destination_id' => $destination->id,
                'destination_name' => $destination->name,
                'destination_type' => $destination->type,
            ]
        );
    }
}
