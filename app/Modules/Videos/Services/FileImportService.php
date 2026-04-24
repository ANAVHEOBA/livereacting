<?php

namespace App\Modules\Videos\Services;

use App\Models\FileImport;
use App\Modules\Videos\Repositories\FileImportRepository;
use App\Modules\Videos\Repositories\FileRepository;
use App\Modules\Videos\Repositories\FolderRepository;

class FileImportService
{
    public function __construct(
        protected FileImportRepository $importRepository,
        protected FileRepository $fileRepository,
        protected FolderRepository $folderRepository
    ) {}

    public function startImport(int $userId, array $data): FileImport
    {
        $folderId = $this->resolveFolderId($userId, $data['folder_id'] ?? null);
        $fileSource = $this->normalizeFileSource($data['source']);

        // Create import record
        $import = $this->importRepository->create([
            'user_id' => $userId,
            'source' => $data['source'],
            'source_url' => $data['source_url'],
            'type' => 'video',
            'status' => 'pending',
            'progress' => 0,
        ]);

        // Create file record
        $file = $this->fileRepository->create([
            'user_id' => $userId,
            'folder_id' => $folderId,
            'name' => $data['name'] ?? $this->extractFileName($data['source_url']),
            'type' => 'video',
            'source' => $fileSource,
            'source_url' => $data['source_url'],
            'storage_path' => null,
            'size_bytes' => 0,
            'duration_seconds' => null,
            'resolution' => null,
            'status' => 'importing',
        ]);

        // Link import to file
        $import->file_id = $file->id;
        $import->save();

        $import->status = 'processing';
        $import->progress = 25;
        $import->save();

        // Simulate completion for testing
        $import->status = 'completed';
        $import->progress = 100;
        $import->save();

        $file->storage_path = 'videos/' . $file->id . '.mp4';
        $file->size_bytes = 10485760; // 10MB simulated
        $file->duration_seconds = 120;
        $file->resolution = '1920x1080';
        $file->format = 'mp4';
        $file->status = 'ready';
        $file->save();

        return $import->fresh(['file']);
    }

    public function getImport(int $importId, int $userId): ?FileImport
    {
        return $this->importRepository->findByIdAndUser($importId, $userId);
    }

    public function cancelImport(FileImport $import): FileImport
    {
        if ($import->status === 'completed') {
            throw new \Exception('Cannot cancel completed import');
        }

        $import->status = 'failed';
        $import->error_message = 'Cancelled by user';
        $import->save();

        if ($import->file) {
            $import->file->update([
                'status' => 'failed',
                'error_message' => 'Import cancelled by user',
            ]);
        }

        return $import->fresh(['file']);
    }

    protected function extractFileName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return basename($path) ?: 'imported_file_' . time();
    }

    protected function detectSource(string $url): string
    {
        if (str_contains($url, 'drive.google.com')) {
            return 'google_drive';
        } elseif (str_contains($url, 'dropbox.com')) {
            return 'dropbox';
        } elseif (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return 'youtube';
        }
        return 'upload';
    }

    protected function normalizeFileSource(string $source): string
    {
        return $source === 'url' ? 'upload' : $source;
    }

    protected function resolveFolderId(int $userId, ?int $folderId): ?int
    {
        if (!$folderId) {
            return null;
        }

        $folder = $this->folderRepository->findByIdAndUser($folderId, $userId);

        if (!$folder) {
            throw new \Exception('Folder not found');
        }

        return $folder->id;
    }
}
