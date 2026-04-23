<?php

namespace App\Modules\Videos\Services;

use App\Models\Folder;
use App\Modules\Videos\Repositories\FolderRepository;
use Illuminate\Database\Eloquent\Collection;

class FolderService
{
    public function __construct(
        protected FolderRepository $folderRepository
    ) {}

    public function createFolder(int $userId, array $data): Folder
    {
        return $this->folderRepository->create([
            'user_id' => $userId,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'video',
        ]);
    }

    public function getUserFolders(int $userId, ?string $type = null): Collection
    {
        return $this->folderRepository->getAllByUser($userId, $type);
    }

    public function getFolder(int $folderId, int $userId): ?Folder
    {
        return $this->folderRepository->findByIdAndUser($folderId, $userId);
    }

    public function deleteFolder(Folder $folder): bool
    {
        // Check if folder has files
        if ($folder->files()->exists()) {
            throw new \Exception('Cannot delete folder with files. Move or delete files first.');
        }

        return $this->folderRepository->delete($folder);
    }
}
