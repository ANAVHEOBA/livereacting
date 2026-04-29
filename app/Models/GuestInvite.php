<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_room_id',
        'project_id',
        'name',
        'email',
        'role',
        'token',
        'status',
        'permissions',
        'invited_at',
        'expires_at',
        'joined_at',
        'metadata',
    ];

    protected $casts = [
        'permissions' => 'array',
        'metadata' => 'array',
        'invited_at' => 'datetime',
        'expires_at' => 'datetime',
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(GuestRoom::class, 'guest_room_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GuestSession::class);
    }
}
