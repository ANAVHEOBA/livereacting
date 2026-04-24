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
        $parent = null;

        if (!empty($data['parent_id'])) {
            $parent = $this->folderRepository->findByIdAndUser($data['parent_id'], $userId);

            if (!$parent) {
                throw new \Exception('Parent folder not found');
            }
        }

        $type = $data['type'] ?? $parent?->type ?? 'video';

        if ($parent && $parent->type !== $type) {
            throw new \Exception('Child folder type must match parent folder type');
        }

        return $this->folderRepository->create([
            'user_id' => $userId,
            'name' => $data['name'],
            'type' => $type,
            'parent_id' => $data['parent_id'] ?? null,
        ]);
    }

    public function getUserFolders(int $userId, array $filters = []): Collection
    {
        return $this->folderRepository->getAllByUser($userId, $filters);
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

        if ($folder->children()->exists()) {
            throw new \Exception('Cannot delete folder with child folders. Delete or move child folders first.');
        }

        return $this->folderRepository->delete($folder);
    }
}
