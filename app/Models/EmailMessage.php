<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email_account_id',
        'customer_id',
        'topic_id',
        'topic_confidence',
        'message_id',
        'thread_id',
        'folder',
        'labels',
        'subject',
        'body_content',
        'body_html',
        'body_plain',
        'body_preview',
        'preview',
        'snippet',
        'sender_email',
        'from_email',
        'sender_name',
        'detected_language',
        'detected_topic',
        'language_confidence',
        'sentiment_score',
        'urgency_level',
        'ai_analysis',
        'received_at',
        'status',
        'processing_status',
        'is_read',
        'is_starred',
        'is_important',
        'is_deleted',
        'deleted_at',
        'is_archived',
        'archived_at',
        'is_spam',
        'spam_marked_at',
        'has_attachments',
        'is_reply',
        'replied_to_message_id',
        'to_recipients',
        'cc_recipients',
        'bcc_recipients',
        'in_reply_to',
        'references',
    ];

    protected $casts = [
        'body_content' => 'encrypted',
        'body_html' => 'encrypted',
        'body_plain' => 'encrypted',
        'subject' => 'encrypted',
        'snippet' => 'encrypted',
        'labels' => 'array',
        'ai_analysis' => 'array',
        'to_recipients' => 'array',
        'cc_recipients' => 'array',
        'bcc_recipients' => 'array',
        'received_at' => 'datetime',
        'archived_at' => 'datetime',
        'spam_marked_at' => 'datetime',
        'language_confidence' => 'decimal:2',
        'topic_confidence' => 'decimal:2',
        'sentiment_score' => 'decimal:2',
        'is_reply' => 'boolean',
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'is_important' => 'boolean',
        'is_deleted' => 'boolean',
        'is_archived' => 'boolean',
        'is_spam' => 'boolean',
        'has_attachments' => 'boolean',
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
        return $this->hasOne(EmotionAnalysis::class);
    }

    public function draftResponse(): HasOne
    {
        return $this->hasOne(DraftResponse::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'detected_language', 'code');
    }

    public function repliedToMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'replied_to_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'replied_to_message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->with('emailAccount')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
