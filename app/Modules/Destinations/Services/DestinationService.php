<?php

namespace App\Modules\Destinations\Services;

use App\Models\StreamingDestination;
use App\Modules\Destinations\Repositories\DestinationRepository;
use Carbon\Carbon;
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
        $destination = $this->destinationRepository->create([
            'user_id' => $userId,
            'type' => $data['type'],
            'name' => $data['name'],
            'platform_id' => $data['platform_id'] ?? null,
            'access_token' => $data['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'rtmp_url' => $data['rtmp_url'] ?? null,
            'stream_key' => $data['stream_key'] ?? null,
            'token_expires_at' => isset($data['token_expires_at'])
                ? Carbon::parse($data['token_expires_at'])
                : null,
            'is_valid' => false,
        ]);

        return $this->validateDestination($destination)['destination'];
    }

    public function updateDestination(StreamingDestination $destination, array $data): StreamingDestination
    {
        $payload = [];

        foreach (['name', 'platform_id', 'access_token', 'refresh_token', 'rtmp_url', 'stream_key'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('token_expires_at', $data)) {
            $payload['token_expires_at'] = $data['token_expires_at']
                ? Carbon::parse($data['token_expires_at'])
                : null;
        }

        if (!empty($payload)) {
            $this->destinationRepository->update($destination, $payload);
        }

        return $this->validateDestination($destination->fresh())['destination'];
    }

    public function deleteDestination(StreamingDestination $destination): void
    {
        if ($destination->projects()->exists()) {
            throw new \Exception('Cannot delete a destination linked to one or more projects');
        }

        $this->destinationRepository->delete($destination);
    }

    public function validateDestination(StreamingDestination $destination): array
    {
        $errors = [];

        if ($destination->type === 'rtmp') {
            if (blank($destination->rtmp_url)) {
                $errors[] = 'RTMP URL is required';
            }

            if (blank($destination->stream_key)) {
                $errors[] = 'Stream key is required';
            }
        } else {
            if (blank($destination->access_token)) {
                $errors[] = 'Access token is required';
            }

            if ($destination->isTokenExpired()) {
                $errors[] = 'Access token has expired';
            }
        }

        $isValid = empty($errors);

        $this->destinationRepository->update($destination, [
            'is_valid' => $isValid,
        ]);

        return [
            'valid' => $isValid,
            'errors' => $errors,
            'destination' => $destination->fresh(),
        ];
    }
}
