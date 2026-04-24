<?php

namespace App\Modules\Videos\Repositories;

use App\Models\FileImport;

class FileImportRepository
{
    public function create(array $data): FileImport
    {
        return FileImport::create($data);
    }

    public function findById(int $id): ?FileImport
    {
        return FileImport::find($id);
    }

    public function findByIdAndUser(int $id, int $userId): ?FileImport
    {
        return FileImport::with('file')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function update(FileImport $import, array $data): bool
    {
        return $import->update($data);
    }

    public function delete(FileImport $import): bool
    {
        return $import->delete();
    }
}
