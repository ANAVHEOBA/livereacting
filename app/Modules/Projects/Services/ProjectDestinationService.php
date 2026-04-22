<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Destinations\Repositories\DestinationRepository;

class ProjectDestinationService
{
    public function __construct(
        protected DestinationRepository $destinationRepository
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
    }

    public function unlinkDestination(Project $project, int $destinationId): void
    {
        // Check if destination is linked
        if (!$project->destinations()->where('streaming_destination_id', $destinationId)->exists()) {
            throw new \Exception('Destination not linked to this project');
        }

        // Unlink destination
        $project->destinations()->detach($destinationId);
    }
}
