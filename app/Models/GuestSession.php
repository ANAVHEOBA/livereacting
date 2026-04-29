<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_room_id',
        'guest_invite_id',
        'display_name',
        'role',
        'connection_status',
        'media_state',
        'permissions',
        'last_seen_at',
        'left_at',
    ];

    protected $casts = [
        'media_state' => 'array',
        'permissions' => 'array',
        'last_seen_at' => 'datetime',
        'left_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(GuestRoom::class, 'guest_room_id');
    }

    public function invite(): BelongsTo
    {
        return $this->belongsTo(GuestInvite::class, 'guest_invite_id');
    }
}
