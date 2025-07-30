<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftResponse extends Model
{
    protected $fillable = [
        'email_message_id',
        'ai_generated_content',
        'response_context',
        'status',
        'provider_draft_id',
        'ai_confidence_score',
        'template_used',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'ai_generated_content' => 'encrypted',
        'response_context' => 'encrypted:array',
        'review_notes' => 'encrypted',
        'ai_confidence_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
