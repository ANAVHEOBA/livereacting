<?php

namespace App\Modules\Projects\Repositories;

use App\Models\LiveStream;
use App\Models\Project;

class LiveStreamRepository
{
    public function create(array $data): LiveStream
    {
        return LiveStream::create($data);
    }

    public function findById(int $id): ?LiveStream
    {
        return LiveStream::find($id);
    }

    public function findActiveByProject(int $projectId): ?LiveStream
    {
        return LiveStream::where('project_id', $projectId)
            ->whereIn('status', ['preparing', 'live'])
            ->latest()
            ->first();
    }

    public function update(LiveStream $liveStream, array $data): bool
    {
        return $liveStream->update($data);
    }
}
