<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'audit_log';

    protected $fillable = [
        'case_id',
        'actor_type',
        'actor_id',
        'action',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public static function record(?int $caseId, string $actorType, ?int $actorId, string $action, array $payload = []): self
    {
        return static::create([
            'case_id' => $caseId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
