<?php

namespace App\Modules\Guests\Services;

use App\Models\GuestInvite;
use App\Models\GuestRoom;
use App\Models\GuestSession;
use App\Models\Project;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Projects\Services\HistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestService
{
    public function __construct(
        protected BillingService $billingService,
        protected HistoryService $historyService
    ) {}

    public function getRoom(Project $project): ?GuestRoom
    {
        return $project->guestRoom()->with(['invites', 'sessions'])->first();
    }

    public function upsertRoom(Project $project, int $userId, array $data): GuestRoom
    {
        $requestedMaxGuests = $data['max_guests'] ?? $project->guestRoom?->max_guests ?? 1;
        $this->billingService->assertCanManageGuests($project, (int) $requestedMaxGuests);

        $room = $project->guestRoom ?: GuestRoom::create([
            'project_id' => $project->id,
            'user_id' => $userId,
            'slug' => $this->uniqueRoomSlug($project),
            'status' => 'ready',
            'max_guests' => $requestedMaxGuests,
            'host_notes' => $data['host_notes'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);

        $room->update([
            'status' => $data['status'] ?? $room->status,
            'max_guests' => $requestedMaxGuests,
            'host_notes' => array_key_exists('host_notes', $data) ? $data['host_notes'] : $room->host_notes,
            'settings' => array_key_exists('settings', $data) ? ($data['settings'] ?? []) : ($room->settings ?? []),
        ]);

        $this->historyService->logAction(
            $project,
            'guest_room_updated',
            'Guest room updated',
            ['guest_room_id' => $room->id, 'max_guests' => $room->max_guests]
        );

        return $room->fresh(['invites', 'sessions']);
    }

    public function createInvite(Project $project, GuestRoom $room, array $data): GuestInvite
    {
        $activeInvitesCount = $room->invites()->whereIn('status', ['pending', 'accepted', 'joined'])->count();

        if ($activeInvitesCount >= $room->max_guests) {
            throw new \Exception('Guest room invite capacity reached');
        }

        $invite = GuestInvite::create([
            'guest_room_id' => $room->id,
            'project_id' => $project->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'] ?? 'guest',
            'token' => Str::random(48),
            'status' => 'pending',
            'permissions' => $data['permissions'] ?? $this->defaultPermissions(),
            'invited_at' => now(),
            'expires_at' => isset($data['expires_in_hours']) ? now()->addHours((int) $data['expires_in_hours']) : now()->addHours(24),
            'metadata' => $data['metadata'] ?? [],
        ]);

        $this->historyService->logAction(
            $project,
            'guest_invited',
            'Guest invited',
            ['guest_invite_id' => $invite->id, 'guest_name' => $invite->name]
        );

        return $invite->fresh();
    }

    public function updateInvite(Project $project, GuestInvite $invite, array $data): GuestInvite
    {
        $invite->update([
            'name' => $data['name'] ?? $invite->name,
            'email' => array_key_exists('email', $data) ? $data['email'] : $invite->email,
            'role' => $data['role'] ?? $invite->role,
            'status' => $data['status'] ?? $invite->status,
            'permissions' => array_key_exists('permissions', $data) ? ($data['permissions'] ?? []) : ($invite->permissions ?? []),
            'metadata' => array_key_exists('metadata', $data) ? ($data['metadata'] ?? []) : ($invite->metadata ?? []),
        ]);

        $this->historyService->logAction(
            $project,
            'guest_invite_updated',
            'Guest invite updated',
            ['guest_invite_id' => $invite->id]
        );

        return $invite->fresh();
    }

    public function removeInvite(Project $project, GuestInvite $invite): void
    {
        $invite->update(['status' => 'revoked']);
        $invite->delete();

        $this->historyService->logAction(
            $project,
            'guest_invite_removed',
            'Guest invite removed',
            ['guest_invite_id' => $invite->id]
        );
    }

    public function getInviteByToken(string $token): ?GuestInvite
    {
        return GuestInvite::query()
            ->with('room.project')
            ->where('token', $token)
            ->first();
    }

    public function acceptInvite(string $token, array $data): array
    {
        $invite = $this->getInviteByToken($token);

        if (! $invite) {
            throw new \Exception('Guest invite not found');
        }

        if ($invite->status === 'revoked') {
            throw new \Exception('Guest invite has been revoked');
        }

        if ($invite->expires_at && $invite->expires_at->isPast()) {
            throw new \Exception('Guest invite has expired');
        }

        return DB::transaction(function () use ($invite, $data) {
            $session = GuestSession::create([
                'guest_room_id' => $invite->guest_room_id,
                'guest_invite_id' => $invite->id,
                'display_name' => $data['display_name'] ?? $invite->name,
                'role' => $invite->role,
                'connection_status' => 'joined',
                'media_state' => [
                    'audio_enabled' => (bool) ($data['audio_enabled'] ?? true),
                    'video_enabled' => (bool) ($data['video_enabled'] ?? true),
                    'screen_enabled' => false,
                ],
                'permissions' => $invite->permissions ?? $this->defaultPermissions(),
                'last_seen_at' => now(),
            ]);

            $invite->update([
                'status' => 'joined',
                'joined_at' => now(),
            ]);

            $signaling = $this->buildSignalingPayload($invite->room, [
                'session_id' => (string) $session->id,
                'role' => $session->role,
                'display_name' => $session->display_name,
                'permissions' => $session->permissions ?? [],
            ]);

            return [
                'invite' => $invite->fresh('room.project'),
                'session' => $session->fresh(),
                'signaling' => $signaling,
            ];
        });
    }

    public function getSession(GuestRoom $room, int $sessionId): ?GuestSession
    {
        return $room->sessions()->where('id', $sessionId)->first();
    }

    public function updateSessionState(Project $project, GuestSession $session, array $data): GuestSession
    {
        $mediaState = array_merge($session->media_state ?? [], $data['media_state'] ?? []);

        $session->update([
            'connection_status' => $data['connection_status'] ?? $session->connection_status,
            'media_state' => $mediaState,
            'permissions' => array_key_exists('permissions', $data) ? ($data['permissions'] ?? []) : ($session->permissions ?? []),
            'last_seen_at' => now(),
            'left_at' => ($data['connection_status'] ?? null) === 'offline' ? now() : $session->left_at,
        ]);

        $this->historyService->logAction(
            $project,
            'guest_session_updated',
            'Guest session updated',
            ['guest_session_id' => $session->id, 'connection_status' => $session->connection_status]
        );

        return $session->fresh();
    }

    public function buildHostSignalingPayload(GuestRoom $room, int $userId): array
    {
        return $this->buildSignalingPayload($room, [
            'session_id' => 'host-'.$userId,
            'role' => 'host',
            'display_name' => 'Host',
            'permissions' => array_merge($this->defaultPermissions(), [
                'can_manage_room' => true,
                'can_remove_guests' => true,
            ]),
        ]);
    }

    public function buildSignalingPayload(GuestRoom $room, array $identity): array
    {
        $token = $this->signRoomToken([
            'room_id' => $room->id,
            'room_slug' => $room->slug,
            'project_id' => $room->project_id,
            'session_id' => $identity['session_id'],
            'role' => $identity['role'],
            'display_name' => $identity['display_name'],
            'permissions' => $identity['permissions'] ?? [],
            'exp' => now()->addSeconds((int) config('streaming.mediasoup.room_token_ttl', 7200))->timestamp,
        ]);

        return [
            'signaling_url' => config('streaming.mediasoup.signaling_url'),
            'room_slug' => $room->slug,
            'token' => $token,
            'ice_servers' => $this->iceServers(),
            'media_codecs' => $this->mediaCodecs(),
            'transport' => [
                'listen_host' => config('streaming.mediasoup.listen_host'),
                'listen_port' => config('streaming.mediasoup.listen_port'),
                'rtc_listen_ip' => config('streaming.mediasoup.rtc_listen_ip'),
                'rtc_announced_address' => config('streaming.mediasoup.rtc_announced_address'),
            ],
        ];
    }

    protected function signRoomToken(array $payload): string
    {
        $serialized = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $secret = (string) (config('streaming.engine.callback_secret') ?: config('app.key'));
        $signature = hash_hmac('sha256', $serialized, $secret);

        return $serialized.'.'.$signature;
    }

    protected function uniqueRoomSlug(Project $project): string
    {
        $base = Str::slug($project->name ?: 'guest-room') ?: 'guest-room';

        return $base.'-'.$project->id;
    }

    protected function defaultPermissions(): array
    {
        return [
            'can_publish_audio' => true,
            'can_publish_video' => true,
            'can_share_screen' => true,
            'can_use_chat' => true,
        ];
    }

    protected function iceServers(): array
    {
        $urls = config('streaming.turn.urls', []);

        if (empty($urls)) {
            return [];
        }

        return [[
            'urls' => $urls,
            'username' => config('streaming.turn.username'),
            'credential' => config('streaming.turn.credential'),
        ]];
    }

    protected function mediaCodecs(): array
    {
        return [
            [
                'kind' => 'audio',
                'mimeType' => 'audio/opus',
                'clockRate' => 48000,
                'channels' => 2,
            ],
            [
                'kind' => 'video',
                'mimeType' => 'video/VP8',
                'clockRate' => 90000,
                'parameters' => [],
            ],
            [
                'kind' => 'video',
                'mimeType' => 'video/H264',
                'clockRate' => 90000,
                'parameters' => [
                    'packetization-mode' => 1,
                    'profile-level-id' => '42e01f',
                    'level-asymmetry-allowed' => 1,
                ],
            ],
        ];
    }
}
