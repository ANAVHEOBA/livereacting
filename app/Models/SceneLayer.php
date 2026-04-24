<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SceneLayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'scene_id',
        'user_id',
        'file_id',
        'type',
        'name',
        'content',
        'sort_order',
        'is_visible',
        'position',
        'settings',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'position' => 'array',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
