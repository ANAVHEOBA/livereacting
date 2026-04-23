<?php

namespace App\Modules\Videos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Videos\Requests\ImportFileRequest;
use App\Modules\Videos\Resources\FileImportResource;
use App\Modules\Videos\Services\FileImportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileImportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FileImportService $fileImportService
    ) {}

    public function import(ImportFileRequest $request): JsonResponse
    {
        try {
            $import = $this->fileImportService->startImport(
                $request->user()->id,
                $request->validated()
            );

            return $this->success(
                new FileImportResource($import),
                'File import started successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $import = $this->fileImportService->getImport($id, $request->user()->id);

        if (!$import) {
            return $this->error('Import not found', 404);
        }

        return $this->success(new FileImportResource($import));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $import = $this->fileImportService->getImport($id, $request->user()->id);

        if (!$import) {
            return $this->error('Import not found', 404);
        }

        try {
            $cancelledImport = $this->fileImportService->cancelImport($import);

            return $this->success(
                new FileImportResource($cancelledImport),
                'Import cancelled successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
