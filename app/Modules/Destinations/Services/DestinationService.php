<?php

namespace App\Modules\Destinations\Services;

use App\Models\StreamingDestination;
use App\Modules\Destinations\Repositories\DestinationRepository;
use Illuminate\Database\Eloquent\Collection;

class DestinationService
{
    public function __construct(
        protected DestinationRepository $destinationRepository
    ) {}

    public function getUserDestinations(int $userId, ?string $type = null): Collection
    {
        return $this->destinationRepository->getAllByUser($userId, $type);
    }

    public function getDestination(int $destinationId, int $userId): ?StreamingDestination
    {
        return $this->destinationRepository->findByIdAndUser($destinationId, $userId);
    }

    public function createDestination(int $userId, array $data): StreamingDestination
    {
        return $this->destinationRepository->create([
            'user_id' => $userId,
            'type' => $data['type'],
            'name' => $data['name'],
            'platform_id' => $data['platform_id'] ?? null,
            'rtmp_url' => $data['rtmp_url'] ?? null,
            'stream_key' => $data['stream_key'] ?? null,
            'is_valid' => true,
        ]);
    }
}
