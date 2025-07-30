<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentMessage extends Model
{
    protected $fillable = [
        'original_email_id',
        'customer_id',
        'sent_by',
        'subject',
        'content',
        'language',
        'sent_at',
        'response_time_hours',
        'was_ai_generated',
        'ai_confidence_score',
        'template_used',
        'delivery_status',
    ];

    protected $casts = [
        'subject' => 'encrypted',
        'content' => 'encrypted',
        'sent_at' => 'datetime',
        'response_time_hours' => 'decimal:2',
        'was_ai_generated' => 'boolean',
        'ai_confidence_score' => 'decimal:2',
    ];

    public function originalEmail(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'original_email_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
