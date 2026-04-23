<?php

namespace App\Modules\Videos\Services;

use App\Models\File;
use App\Modules\Videos\Repositories\FileRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class FileService
{
    public function __construct(
        protected FileRepository $fileRepository
    ) {}

    public function getUserFiles(int $userId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->fileRepository->getAllByUser($userId, $filters, $perPage);
    }

    public function getFile(int $fileId, int $userId): ?File
    {
        return $this->fileRepository->findByIdAndUser($fileId, $userId);
    }

    public function renameFile(File $file, string $newName): File
    {
        $this->fileRepository->update($file, ['name' => $newName]);
        return $file->fresh();
    }

    public function deleteFile(File $file): bool
    {
        // TODO: Delete from storage
        return $this->fileRepository->delete($file);
    }
}
