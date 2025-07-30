<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_number',
        'content',
        'embedding',
        'start_position',
        'end_position',
        'metadata',
        'elasticsearch_id',
    ];

    protected $casts = [
        'content' => 'encrypted',
        'metadata' => 'array',
        'chunk_number' => 'integer',
        'start_position' => 'integer',
        'end_position' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function getEmbeddingAttribute($value): ?array
    {
        return $value ? json_decode($value, true) : null;
    }

    public function setEmbeddingAttribute($value): void
    {
        $this->attributes['embedding'] = $value ? json_encode($value) : null;
    }
}
