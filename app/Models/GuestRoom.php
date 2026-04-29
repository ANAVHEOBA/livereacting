<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'slug',
        'status',
        'max_guests',
        'host_notes',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(GuestInvite::class)->latest();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GuestSession::class)->latest();
    }
}
