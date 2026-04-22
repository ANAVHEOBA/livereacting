<?php

namespace App\Modules\Destinations\Repositories;

use App\Models\StreamingDestination;
use Illuminate\Database\Eloquent\Collection;

class DestinationRepository
{
    public function create(array $data): StreamingDestination
    {
        return StreamingDestination::create($data);
    }

    public function findById(int $id): ?StreamingDestination
    {
        return StreamingDestination::find($id);
    }

    public function findByIdAndUser(int $id, int $userId): ?StreamingDestination
    {
        return StreamingDestination::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function getAllByUser(int $userId, ?string $type = null): Collection
    {
        $query = StreamingDestination::where('user_id', $userId);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function update(StreamingDestination $destination, array $data): bool
    {
        return $destination->update($data);
    }

    public function delete(StreamingDestination $destination): bool
    {
        return $destination->delete();
    }
}
