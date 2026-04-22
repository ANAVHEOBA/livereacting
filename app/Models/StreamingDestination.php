<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StreamingDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'platform_id',
        'access_token',
        'refresh_token',
        'rtmp_url',
        'stream_key',
        'is_valid',
        'token_expires_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'stream_key',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'token_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_destinations');
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public function needsReconnection(): bool
    {
        return !$this->is_valid || $this->isTokenExpired();
    }
}
