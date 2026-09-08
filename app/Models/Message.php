<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_calls',
        'tool_name',
        'tool_call_id',
        'wa_message_id',
    ];

    protected $casts = [
        'tool_calls' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class);
    }
}
