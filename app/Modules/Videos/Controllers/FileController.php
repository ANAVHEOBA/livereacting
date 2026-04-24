<?php

namespace App\Modules\Videos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Videos\Requests\RenameFileRequest;
use App\Modules\Videos\Resources\FileResource;
use App\Modules\Videos\Services\FileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FileService $fileService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'folder_id' => $request->query('folder_id'),
            'search' => $request->query('search'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
        ];

        $perPage = $request->query('per_page', 20);

        $files = $this->fileService->getUserFiles(
            $request->user()->id,
            $filters,
            $perPage
        );

        return $this->success([
            'files' => FileResource::collection($files->items()),
            'total' => $files->total(),
            'per_page' => $files->perPage(),
            'current_page' => $files->currentPage(),
            'last_page' => $files->lastPage(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $file = $this->fileService->getFile($id, $request->user()->id);

        if (!$file) {
            return $this->error('File not found', 404);
        }

        return $this->success(new FileResource($file));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $file = $this->fileService->getFile($id, $request->user()->id);

        if (!$file) {
            return $this->error('File not found', 404);
        }

        try {
            $this->fileService->deleteFile($file);

            return $this->success(null, 'File deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function rename(RenameFileRequest $request, int $id): JsonResponse
    {
        $file = $this->fileService->getFile($id, $request->user()->id);

        if (!$file) {
            return $this->error('File not found', 404);
        }

        try {
            $updatedFile = $this->fileService->renameFile($file, $request->validated()['name']);

            return $this->success(
                new FileResource($updatedFile),
                'File renamed successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
