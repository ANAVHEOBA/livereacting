<?php

namespace App\Modules\Videos\Repositories;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Collection;

class FolderRepository
{
    public function create(array $data): Folder
    {
        return Folder::create($data);
    }

    public function findById(int $id): ?Folder
    {
        return Folder::find($id);
    }

    public function findByIdAndUser(int $id, int $userId): ?Folder
    {
        return Folder::where('id', $id)->where('user_id', $userId)->first();
    }

    public function getAllByUser(int $userId, array $filters = []): Collection
    {
        $query = Folder::where('user_id', $userId);

        if (array_key_exists('type', $filters) && $filters['type']) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('parent_id', $filters)) {
            if ($filters['parent_id'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        return $query
            ->withCount('files')
            ->orderBy('name')
            ->get();
    }

    public function delete(Folder $folder): bool
    {
        return $folder->delete();
    }
}
