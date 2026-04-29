<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteractiveResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'interactive_element_id',
        'participant_name',
        'response_key',
        'message',
        'is_correct',
        'payload',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function element(): BelongsTo
    {
        return $this->belongsTo(InteractiveElement::class, 'interactive_element_id');
    }
}
