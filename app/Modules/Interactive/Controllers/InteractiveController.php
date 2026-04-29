<?php

namespace App\Modules\Interactive\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interactive\Requests\CreateInteractiveElementRequest;
use App\Modules\Interactive\Requests\FeatureInteractiveResponseRequest;
use App\Modules\Interactive\Requests\SubmitInteractiveResponseRequest;
use App\Modules\Interactive\Requests\UpdateInteractiveElementRequest;
use App\Modules\Interactive\Resources\InteractiveElementResource;
use App\Modules\Interactive\Resources\InteractiveResponseResource;
use App\Modules\Interactive\Services\InteractiveService;
use App\Modules\Projects\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InteractiveController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected InteractiveService $interactiveService
    ) {}

    public function index(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        return $this->success([
            'interactive_elements' => InteractiveElementResource::collection(
                $this->interactiveService->getProjectElements($project)
            ),
        ]);
    }

    public function store(CreateInteractiveElementRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        try {
            $element = $this->interactiveService->createElement($project, $request->user()->id, $request->validated());

            return $this->success(new InteractiveElementResource($element), 'Interactive element created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function show(Request $request, int $projectId, int $elementId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $element = $this->interactiveService->getElement($project, $elementId);

        if (! $element) {
            return $this->error('Interactive element not found', 404);
        }

        return $this->success(new InteractiveElementResource($element));
    }

    public function update(UpdateInteractiveElementRequest $request, int $projectId, int $elementId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $element = $this->interactiveService->getElement($project, $elementId);

        if (! $element) {
            return $this->error('Interactive element not found', 404);
        }

        try {
            $element = $this->interactiveService->updateElement($project, $element, $request->validated());

            return $this->success(new InteractiveElementResource($element), 'Interactive element updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, int $projectId, int $elementId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $element = $this->interactiveService->getElement($project, $elementId);

        if (! $element) {
            return $this->error('Interactive element not found', 404);
        }

        $this->interactiveService->deleteElement($project, $element);

        return $this->success(null, 'Interactive element deleted successfully');
    }

    public function activate(Request $request, int $projectId, int $elementId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $element = $this->interactiveService->getElement($project, $elementId);

        if (! $element) {
            return $this->error('Interactive element not found', 404);
        }

        return $this->success(
            new InteractiveElementResource($this->interactiveService->activateElement($project, $element)),
            'Interactive element activated successfully'
        );
    }

    public function respond(SubmitInteractiveResponseRequest $request, int $projectId, int $elementId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $element = $this->interactiveService->getElement($project, $elementId);

        if (! $element) {
            return $this->error('Interactive element not found', 404);
        }

        try {
            $response = $this->interactiveService->submitResponse($element, $request->validated());

            return $this->success(new InteractiveResponseResource($response), 'Interactive response submitted successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function featureResponse(FeatureInteractiveResponseRequest $request, int $projectId, int $elementId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $element = $this->interactiveService->getElement($project, $elementId);

        if (! $element) {
            return $this->error('Interactive element not found', 404);
        }

        $response = $element->responses()->where('id', $request->validated()['response_id'])->first();

        if (! $response) {
            return $this->error('Interactive response not found', 404);
        }

        return $this->success(
            new InteractiveElementResource($this->interactiveService->featureResponse($element, $response)),
            'Interactive response featured successfully'
        );
    }
}
