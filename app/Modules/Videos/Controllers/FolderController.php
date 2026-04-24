<?php

namespace App\Modules\Videos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Videos\Requests\CreateFolderRequest;
use App\Modules\Videos\Resources\FolderResource;
use App\Modules\Videos\Services\FolderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FolderService $folderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $folders = $this->folderService->getUserFolders(
            $request->user()->id,
            [
                'parent_id' => $request->query('parent_id'),
                'type' => $request->query('type'),
            ]
        );

        return $this->success([
            'folders' => FolderResource::collection($folders),
        ]);
    }

    public function store(CreateFolderRequest $request): JsonResponse
    {
        try {
            $folder = $this->folderService->createFolder(
                $request->user()->id,
                $request->validated()
            );

            return $this->success(
                new FolderResource($folder),
                'Folder created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $folder = $this->folderService->getFolder($id, $request->user()->id);

        if (!$folder) {
            return $this->error('Folder not found', 404);
        }

        try {
            $this->folderService->deleteFolder($folder);

            return $this->success(null, 'Folder deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
