<?php

namespace App\Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Services\StudioHealthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudioHealthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StudioHealthService $studioHealthService
    ) {}

    public function show(): JsonResponse
    {
        return $this->success($this->studioHealthService->report());
    }
}
