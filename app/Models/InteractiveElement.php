<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InteractiveElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'scene_id',
        'user_id',
        'type',
        'name',
        'prompt',
        'status',
        'is_visible',
        'sort_order',
        'settings',
        'results',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'settings' => 'array',
        'results' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(InteractiveResponse::class)->latest();
    }
}
