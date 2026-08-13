<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Extraction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'case_id',
        'document_id',
        'field_key',
        'field_value',
        'value_type',
        'confidence',
        'source_chunk_id',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function sourceChunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class, 'source_chunk_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
