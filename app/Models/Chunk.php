<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'page_number',
        'chunk_index',
        'content',
        'bbox',
        'embedding',
    ];

    protected $casts = [
        'bbox' => 'array',
        'embedding' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
