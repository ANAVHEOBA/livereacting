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

    public function getAllByUser(int $userId, ?string $type = null): Collection
    {
        $query = Folder::where('user_id', $userId);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('name')->get();
    }

    public function delete(Folder $folder): bool
    {
        return $folder->delete();
    }
}
