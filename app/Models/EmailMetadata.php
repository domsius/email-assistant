<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailMetadata extends Model
{
    protected $table = 'email_metadata';

    protected $fillable = [
        'email_account_id',
        'customer_id',
        'topic_id',
        'topic_confidence',
        'message_id',
        'thread_id',
        'subject_hash',
        'content_hash',
        'sender_email',
        'sender_name',
        'detected_language',
        'language_confidence',
        'received_at',
        'status',
        'is_reply',
        'replied_to_message_id',
        'word_count',
        'sentiment_score',
        'urgency_score',
        'processing_timestamp',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processing_timestamp' => 'datetime',
        'language_confidence' => 'decimal:2',
        'topic_confidence' => 'decimal:2',
        'sentiment_score' => 'decimal:2',
        'urgency_score' => 'decimal:2',
        'is_reply' => 'boolean',
    ];

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function emotionAnalysis(): HasOne
    {
        return $this->hasOne(EmotionAnalysis::class, 'email_message_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'email_message_id');
    }

    public function repliedToMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMetadata::class, 'replied_to_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EmailMetadata::class, 'replied_to_message_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }
}
