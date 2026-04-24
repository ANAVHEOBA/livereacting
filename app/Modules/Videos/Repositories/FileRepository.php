<?php

namespace App\Modules\Videos\Repositories;

use App\Models\File;
use Illuminate\Pagination\LengthAwarePaginator;

class FileRepository
{
    public function create(array $data): File
    {
        return File::create($data);
    }

    public function findById(int $id): ?File
    {
        return File::find($id);
    }

    public function findByIdAndUser(int $id, int $userId): ?File
    {
        return File::where('id', $id)->where('user_id', $userId)->first();
    }

    public function getAllByUser(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = File::where('user_id', $userId);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['folder_id'])) {
            $query->where('folder_id', $filters['folder_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function update(File $file, array $data): bool
    {
        return $file->update($data);
    }

    public function delete(File $file): bool
    {
        return $file->delete();
    }
}
