<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'company_id',
        'uploaded_by',
        'title',
        'filename',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'status',
        'error_message',
        'chunk_count',
        'metadata',
        'elasticsearch_index',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'chunk_count' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsProcessed(int $chunkCount): void
    {
        $this->update([
            'status' => 'processed',
            'chunk_count' => $chunkCount,
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
