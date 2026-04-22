<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStream extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'format',
        'duration',
        'started_at',
        'ended_at',
        'metadata',
        'error_message',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
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

    public function isActive(): bool
    {
        return in_array($this->status, ['preparing', 'live']);
    }

    public function canBeStopped(): bool
    {
        return in_array($this->status, ['preparing', 'live']);
    }
}
