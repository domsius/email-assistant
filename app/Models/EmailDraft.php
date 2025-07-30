<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_account_id',
        'to',
        'cc',
        'bcc',
        'subject',
        'body',
        'in_reply_to',
        'references',
        'action',
        'original_email_id',
        'attachments',
        'last_saved_at',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'last_saved_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function originalEmail(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'original_email_id');
    }
}
