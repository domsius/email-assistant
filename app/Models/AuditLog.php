<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // Only has created_at

    protected $fillable = [
        'company_id',
        'user_id',
        'event_type',
        'auditable_type',
        'auditable_id',
        'description',
        'data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    // Common event types for GDPR compliance
    const EVENT_EMAIL_ACCESSED = 'email.accessed';

    const EVENT_EMAIL_DELETED = 'email.deleted';

    const EVENT_EMAIL_EXPORTED = 'email.exported';

    const EVENT_EMAIL_PROCESSED = 'email.processed';

    const EVENT_DATA_EXPORTED = 'data.exported';

    const EVENT_DATA_DELETED = 'data.deleted';

    const EVENT_ACCOUNT_CONNECTED = 'account.connected';

    const EVENT_ACCOUNT_DISCONNECTED = 'account.disconnected';

    const EVENT_DOCUMENT_UPLOADED = 'document.uploaded';

    const EVENT_DOCUMENT_DELETED = 'document.deleted';

    const EVENT_AI_RESPONSE_GENERATED = 'ai.response_generated';

    const EVENT_LOGIN = 'auth.login';

    const EVENT_LOGOUT = 'auth.logout';

    /**
     * Get the company that owns the audit log.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user that triggered the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable model.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
