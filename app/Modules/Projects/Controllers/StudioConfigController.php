<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Services\StreamConfigService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudioConfigController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StreamConfigService $streamConfigService
    ) {}

    public function show(): JsonResponse
    {
        return $this->success($this->streamConfigService->getStudioConfig());
    }
}
