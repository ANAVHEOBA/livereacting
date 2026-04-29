<?php

namespace App\Modules\Videos\Services;

use App\Models\File;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Database\Eloquent\Collection;

class PlaylistService
{
    public function getUserPlaylists(int $userId, ?int $projectId = null): Collection
    {
        return Playlist::query()
            ->where('user_id', $userId)
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->with('items.file')
            ->latest()
            ->get();
    }

    public function getPlaylist(int $playlistId, int $userId): ?Playlist
    {
        return Playlist::query()
            ->where('user_id', $userId)
            ->where('id', $playlistId)
            ->with('items.file')
            ->first();
    }

    public function createPlaylist(int $userId, array $data): Playlist
    {
        $playlist = Playlist::create([
            'user_id' => $userId,
            'project_id' => $data['project_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $playlist->fresh('items.file');
    }

    public function updatePlaylist(Playlist $playlist, array $data): Playlist
    {
        $playlist->update([
            'project_id' => $data['project_id'] ?? $playlist->project_id,
            'name' => $data['name'] ?? $playlist->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $playlist->description,
        ]);

        return $playlist->fresh('items.file');
    }

    public function deletePlaylist(Playlist $playlist): void
    {
        $playlist->delete();
    }

    public function addItem(Playlist $playlist, File $file, array $data = []): Playlist
    {
        PlaylistItem::create([
            'playlist_id' => $playlist->id,
            'file_id' => $file->id,
            'sort_order' => (int) $playlist->items()->max('sort_order') + 1,
            'start_offset_seconds' => $data['start_offset_seconds'] ?? 0,
        ]);

        return $playlist->fresh('items.file');
    }

    public function removeItem(Playlist $playlist, int $itemId): Playlist
    {
        $item = $playlist->items()->where('id', $itemId)->first();

        if (! $item) {
            throw new \Exception('Playlist item not found');
        }

        $item->delete();

        $playlist->items()->orderBy('sort_order')->get()->values()->each(function (PlaylistItem $playlistItem, int $index) {
            $playlistItem->update(['sort_order' => $index + 1]);
        });

        return $playlist->fresh('items.file');
    }
}
