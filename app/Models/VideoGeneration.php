<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoGeneration extends Model
{
    protected $fillable = [
        'conversation_id',
        'provider',
        'external_job_id',
        'prompt',
        'status',
        'output_url',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
