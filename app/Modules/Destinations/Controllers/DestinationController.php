<?php

namespace App\Modules\Destinations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Destinations\Requests\CreateDestinationRequest;
use App\Modules\Destinations\Requests\UpdateDestinationRequest;
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

    public function show(Request $request, int $id): JsonResponse
    {
        $destination = $this->destinationService->getDestination($id, $request->user()->id);

        if (!$destination) {
            return $this->error('Destination not found', 404);
        }

        return $this->success(new DestinationResource($destination));
    }

    public function store(CreateDestinationRequest $request): JsonResponse
    {
        try {
            $destination = $this->destinationService->createDestination(
                $request->user()->id,
                $request->validated()
            );

            return $this->success(
                new DestinationResource($destination),
                'Destination created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function update(UpdateDestinationRequest $request, int $id): JsonResponse
    {
        $destination = $this->destinationService->getDestination($id, $request->user()->id);

        if (!$destination) {
            return $this->error('Destination not found', 404);
        }

        try {
            $destination = $this->destinationService->updateDestination($destination, $request->validated());

            return $this->success(
                new DestinationResource($destination),
                'Destination updated successfully'
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $destination = $this->destinationService->getDestination($id, $request->user()->id);

        if (!$destination) {
            return $this->error('Destination not found', 404);
        }

        try {
            $this->destinationService->deleteDestination($destination);

            return $this->success(null, 'Destination deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function validate(Request $request, int $id): JsonResponse
    {
        $destination = $this->destinationService->getDestination($id, $request->user()->id);

        if (!$destination) {
            return $this->error('Destination not found', 404);
        }

        $validation = $this->destinationService->validateDestination($destination);

        if (!$validation['valid']) {
            return $this->error('Destination validation failed', 400, $validation['errors']);
        }

        return $this->success([
            'valid' => true,
            'destination' => new DestinationResource($validation['destination']),
        ], 'Destination is valid');
    }
}
