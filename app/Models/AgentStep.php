<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'message_id',
        'step_type',
        'tool_name',
        'tool_input',
        'tool_output',
        'provider_used',
    ];

    protected $casts = [
        'tool_input' => 'array',
        'tool_output' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
