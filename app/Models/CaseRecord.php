<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseRecord extends Model
{
    use HasFactory;

    protected $table = 'cases';

    protected $fillable = [
        'vertical',
        'reference',
        'status',
        'summary',
        'assigned_to',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class, 'case_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'case_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'case_id');
    }
}
