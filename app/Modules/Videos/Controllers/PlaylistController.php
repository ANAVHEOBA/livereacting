<?php

namespace App\Modules\Videos\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Videos\Requests\AddPlaylistItemRequest;
use App\Modules\Videos\Requests\CreatePlaylistRequest;
use App\Modules\Videos\Requests\UpdatePlaylistRequest;
use App\Modules\Videos\Resources\PlaylistResource;
use App\Modules\Videos\Services\FileService;
use App\Modules\Videos\Services\PlaylistService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PlaylistService $playlistService,
        protected FileService $fileService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success([
            'playlists' => PlaylistResource::collection(
                $this->playlistService->getUserPlaylists($request->user()->id, $request->query('project_id'))
            ),
        ]);
    }

    public function store(CreatePlaylistRequest $request): JsonResponse
    {
        $playlist = $this->playlistService->createPlaylist($request->user()->id, $request->validated());

        return $this->success(new PlaylistResource($playlist), 'Playlist created successfully', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $playlist = $this->playlistService->getPlaylist($id, $request->user()->id);

        if (! $playlist) {
            return $this->error('Playlist not found', 404);
        }

        return $this->success(new PlaylistResource($playlist));
    }

    public function update(UpdatePlaylistRequest $request, int $id): JsonResponse
    {
        $playlist = $this->playlistService->getPlaylist($id, $request->user()->id);

        if (! $playlist) {
            return $this->error('Playlist not found', 404);
        }

        return $this->success(
            new PlaylistResource($this->playlistService->updatePlaylist($playlist, $request->validated())),
            'Playlist updated successfully'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $playlist = $this->playlistService->getPlaylist($id, $request->user()->id);

        if (! $playlist) {
            return $this->error('Playlist not found', 404);
        }

        $this->playlistService->deletePlaylist($playlist);

        return $this->success(null, 'Playlist deleted successfully');
    }

    public function addItem(AddPlaylistItemRequest $request, int $id): JsonResponse
    {
        $playlist = $this->playlistService->getPlaylist($id, $request->user()->id);

        if (! $playlist) {
            return $this->error('Playlist not found', 404);
        }

        $file = $this->fileService->getFile($request->validated()['file_id'], $request->user()->id);

        if (! $file) {
            return $this->error('File not found', 404);
        }

        return $this->success(
            new PlaylistResource($this->playlistService->addItem($playlist, $file, $request->validated())),
            'Playlist item added successfully'
        );
    }

    public function removeItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $playlist = $this->playlistService->getPlaylist($id, $request->user()->id);

        if (! $playlist) {
            return $this->error('Playlist not found', 404);
        }

        try {
            $playlist = $this->playlistService->removeItem($playlist, $itemId);

            return $this->success(new PlaylistResource($playlist), 'Playlist item removed successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
