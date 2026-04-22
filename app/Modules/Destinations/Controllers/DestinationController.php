<?php

namespace App\Modules\Destinations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Destinations\Resources\DestinationResource;
use App\Modules\Destinations\Services\DestinationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DestinationService $destinationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $destinations = $this->destinationService->getUserDestinations(
            $request->user()->id,
            $type
        );

        return $this->success([
            'destinations' => DestinationResource::collection($destinations),
            'total' => $destinations->count(),
        ]);
    }
}
