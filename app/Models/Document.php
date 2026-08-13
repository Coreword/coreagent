<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'filename',
        'storage_path',
        'mime_type',
        'page_count',
        'doc_type',
        'doc_type_conf',
        'ocr_status',
        'pipeline_status',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class, 'document_id');
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class, 'document_id');
    }
}
