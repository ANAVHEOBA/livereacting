<?php

namespace App\Modules\Videos\Services;

use App\Models\FileImport;
use App\Models\User;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Videos\Repositories\FileImportRepository;
use App\Modules\Videos\Repositories\FileRepository;
use App\Modules\Videos\Repositories\FolderRepository;

class FileImportService
{
    public function __construct(
        protected FileImportRepository $importRepository,
        protected FileRepository $fileRepository,
        protected FolderRepository $folderRepository,
        protected BillingService $billingService
    ) {}

    public function startImport(int $userId, array $data): FileImport
    {
        $folderId = $this->resolveFolderId($userId, $data['folder_id'] ?? null);
        $fileSource = $this->normalizeFileSource($data['source'], $data['source_url']);
        $fileType = $data['type'] ?? 'video';
        $metadata = $this->simulatedAssetMetadata($fileType, 0, $data);

        $user = User::query()->findOrFail($userId);
        $this->billingService->assertCanImportAsset($user, $metadata['size_bytes'], $fileType);

        // Create import record
        $import = $this->importRepository->create([
            'user_id' => $userId,
            'source' => $data['source'],
            'source_url' => $data['source_url'],
            'type' => $fileType,
            'status' => 'pending',
            'progress' => 0,
        ]);

        // Create file record
        $file = $this->fileRepository->create([
            'user_id' => $userId,
            'folder_id' => $folderId,
            'name' => $data['name'] ?? $this->extractFileName($data['source_url']),
            'type' => $fileType,
            'source' => $fileSource,
            'source_url' => $data['source_url'],
            'storage_path' => null,
            'size_bytes' => 0,
            'duration_seconds' => null,
            'resolution' => null,
            'format' => null,
            'codec' => null,
            'status' => 'importing',
            'metadata' => array_merge([
                'provider' => $fileSource,
                'transcode_profile' => $this->transcodeProfileFor($fileType),
                'requested_metadata' => $data['metadata'] ?? [],
            ], $data['metadata'] ?? []),
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

        $metadata = $this->simulatedAssetMetadata($fileType, $file->id, $data);

        $file->storage_path = $metadata['storage_path'];
        $file->size_bytes = $metadata['size_bytes'];
        $file->duration_seconds = $metadata['duration_seconds'];
        $file->resolution = $metadata['resolution'];
        $file->format = $metadata['format'];
        $file->codec = $metadata['codec'];
        $file->status = 'ready';
        $file->metadata = array_merge($file->metadata ?? [], [
            'provider' => $fileSource,
            'transcode_profile' => $this->transcodeProfileFor($fileType),
            'renditions' => $metadata['renditions'],
            'waveform' => $metadata['waveform'],
        ]);
        $file->save();

        $this->billingService->consumeCredits(
            $user,
            $this->creditsCostFor($fileType),
            'Media asset imported',
            'file',
            $file->id,
            ['source' => $fileSource, 'type' => $fileType]
        );

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

        return basename($path) ?: 'imported_file_'.time();
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

    protected function normalizeFileSource(string $source, string $url): string
    {
        if ($source === 'url') {
            return $this->detectSource($url);
        }

        return $source;
    }

    protected function resolveFolderId(int $userId, ?int $folderId): ?int
    {
        if (! $folderId) {
            return null;
        }

        $folder = $this->folderRepository->findByIdAndUser($folderId, $userId);

        if (! $folder) {
            throw new \Exception('Folder not found');
        }

        return $folder->id;
    }

    protected function simulatedAssetMetadata(string $fileType, int $fileId, array $data = []): array
    {
        return match ($fileType) {
            'audio' => [
                'storage_path' => 'audio/'.$fileId.'.mp3',
                'size_bytes' => $data['size_bytes'] ?? 5242880,
                'duration_seconds' => $data['duration_seconds'] ?? 180,
                'resolution' => null,
                'format' => $data['format'] ?? 'mp3',
                'codec' => 'aac',
                'renditions' => [['bitrate' => '128k', 'format' => 'mp3']],
                'waveform' => ['generated' => true],
            ],
            'image' => [
                'storage_path' => 'images/'.$fileId.'.png',
                'size_bytes' => $data['size_bytes'] ?? 2097152,
                'duration_seconds' => null,
                'resolution' => $data['resolution'] ?? '1920x1080',
                'format' => $data['format'] ?? 'png',
                'codec' => null,
                'renditions' => [['resolution' => '1280x720'], ['resolution' => '1920x1080']],
                'waveform' => null,
            ],
            default => [
                'storage_path' => 'videos/'.$fileId.'.mp4',
                'size_bytes' => $data['size_bytes'] ?? 10485760,
                'duration_seconds' => $data['duration_seconds'] ?? 120,
                'resolution' => $data['resolution'] ?? '1920x1080',
                'format' => $data['format'] ?? 'mp4',
                'codec' => 'h264',
                'renditions' => [
                    ['resolution' => '1920x1080', 'bitrate' => '4500k'],
                    ['resolution' => '1280x720', 'bitrate' => '2500k'],
                    ['resolution' => '854x480', 'bitrate' => '1200k'],
                ],
                'waveform' => null,
            ],
        };
    }

    protected function transcodeProfileFor(string $fileType): string
    {
        return match ($fileType) {
            'audio' => 'audio_standard',
            'image' => 'image_passthrough',
            default => 'video_streaming_ladder',
        };
    }

    protected function creditsCostFor(string $fileType): int
    {
        return match ($fileType) {
            'audio' => 2,
            'image' => 1,
            default => 5,
        };
    }
}
