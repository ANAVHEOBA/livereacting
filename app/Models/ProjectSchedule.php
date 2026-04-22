<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'start_at',
        'duration',
        'format',
        'status',
        'error_message',
    ];

    protected $casts = [
        'start_at' => 'datetime',
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

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function canBeCancelled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isPast(): bool
    {
        return $this->start_at->isPast();
    }

    public function isFuture(): bool
    {
        return $this->start_at->isFuture();
    }
}
