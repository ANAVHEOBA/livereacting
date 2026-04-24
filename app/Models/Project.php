<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'thumbnail',
        'status',
        'auto_sync',
        'active_live_id',
        'active_scene_id',
    ];

    protected $casts = [
        'auto_sync' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(StreamingDestination::class, 'project_destinations');
    }

    public function liveStreams(): HasMany
    {
        return $this->hasMany(LiveStream::class);
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('sort_order');
    }

    public function activeLiveStream(): HasOne
    {
        return $this->hasOne(LiveStream::class)
            ->whereIn('status', ['preparing', 'live'])
            ->latest();
    }

    public function activeScene(): BelongsTo
    {
        return $this->belongsTo(Scene::class, 'active_scene_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ProjectSchedule::class);
    }

    public function activeSchedules(): HasMany
    {
        return $this->hasMany(ProjectSchedule::class)
            ->where('status', 'scheduled')
            ->where('start_at', '>', now());
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProjectHistory::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function canBeDeleted(): bool
    {
        return !in_array($this->status, ['live', 'scheduled']);
    }

    public function canBeUpdated(): bool
    {
        return !$this->isLive();
    }

    public function hasActiveLiveStream(): bool
    {
        return $this->activeLiveStream()->exists();
    }

    public function hasValidDestinations(): bool
    {
        return $this->destinations()
            ->where('is_valid', true)
            ->exists();
    }

    public function hasActiveSchedules(): bool
    {
        return $this->activeSchedules()->exists();
    }

    public function hasScenes(): bool
    {
        return $this->scenes()->exists();
    }
}
