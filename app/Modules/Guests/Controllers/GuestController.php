<?php

namespace App\Modules\Guests\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Guests\Requests\AcceptGuestInviteRequest;
use App\Modules\Guests\Requests\CreateGuestInviteRequest;
use App\Modules\Guests\Requests\UpdateGuestInviteRequest;
use App\Modules\Guests\Requests\UpdateGuestSessionRequest;
use App\Modules\Guests\Requests\UpsertGuestRoomRequest;
use App\Modules\Guests\Resources\GuestInviteResource;
use App\Modules\Guests\Resources\GuestRoomResource;
use App\Modules\Guests\Resources\GuestSessionResource;
use App\Modules\Guests\Services\GuestService;
use App\Modules\Projects\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService,
        protected GuestService $guestService
    ) {}

    public function showRoom(Request $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $room = $this->guestService->getRoom($project);

        if (! $room) {
            return $this->success(['room' => null]);
        }

        $room->host_signaling = $this->guestService->buildHostSignalingPayload($room, $request->user()->id);

        return $this->success([
            'room' => new GuestRoomResource($room),
        ]);
    }

    public function upsertRoom(UpsertGuestRoomRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        try {
            $room = $this->guestService->upsertRoom($project, $request->user()->id, $request->validated());
            $room->host_signaling = $this->guestService->buildHostSignalingPayload($room, $request->user()->id);

            return $this->success(new GuestRoomResource($room), 'Guest room saved successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function createInvite(CreateGuestInviteRequest $request, int $projectId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $room = $this->guestService->getRoom($project) ?: $this->guestService->upsertRoom($project, $request->user()->id, []);

        try {
            $invite = $this->guestService->createInvite($project, $room, $request->validated());

            return $this->success(new GuestInviteResource($invite), 'Guest invite created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function updateInvite(UpdateGuestInviteRequest $request, int $projectId, int $inviteId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $room = $this->guestService->getRoom($project);
        $invite = $room?->invites()->where('id', $inviteId)->first();

        if (! $invite) {
            return $this->error('Guest invite not found', 404);
        }

        return $this->success(
            new GuestInviteResource($this->guestService->updateInvite($project, $invite, $request->validated())),
            'Guest invite updated successfully'
        );
    }

    public function destroyInvite(Request $request, int $projectId, int $inviteId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $room = $this->guestService->getRoom($project);
        $invite = $room?->invites()->where('id', $inviteId)->first();

        if (! $invite) {
            return $this->error('Guest invite not found', 404);
        }

        $this->guestService->removeInvite($project, $invite);

        return $this->success(null, 'Guest invite removed successfully');
    }

    public function updateSession(UpdateGuestSessionRequest $request, int $projectId, int $sessionId): JsonResponse
    {
        $project = $this->projectService->getProject($projectId, $request->user()->id);

        if (! $project) {
            return $this->error('Project not found', 404);
        }

        $room = $this->guestService->getRoom($project);
        $session = $room ? $this->guestService->getSession($room, $sessionId) : null;

        if (! $session) {
            return $this->error('Guest session not found', 404);
        }

        return $this->success(
            new GuestSessionResource($this->guestService->updateSessionState($project, $session, $request->validated())),
            'Guest session updated successfully'
        );
    }

    public function previewInvite(string $token): JsonResponse
    {
        $invite = $this->guestService->getInviteByToken($token);

        if (! $invite) {
            return $this->error('Guest invite not found', 404);
        }

        return $this->success([
            'invite' => new GuestInviteResource($invite),
            'room' => new GuestRoomResource($invite->room->loadMissing(['invites', 'sessions'])),
        ]);
    }

    public function acceptInvite(AcceptGuestInviteRequest $request, string $token): JsonResponse
    {
        try {
            $result = $this->guestService->acceptInvite($token, $request->validated());

            return $this->success([
                'invite' => new GuestInviteResource($result['invite']),
                'session' => new GuestSessionResource($result['session']),
                'signaling' => $result['signaling'],
            ], 'Guest invite accepted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
