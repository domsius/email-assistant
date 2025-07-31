<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    protected $fillable = [
        'email_message_id',
        'filename',
        'content_type',
        'size',
        'content_id',
        'content_disposition',
        'storage_path',
        'download_url',
        'thumbnail_url',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    public function isInline(): bool
    {
        return ! empty($this->content_id) && str_contains($this->content_disposition ?? '', 'inline');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->content_type ?? '', 'image/');
    }

    public function getFormattedSizeAttribute(): string
    {
        $size = $this->size;

        if ($size < 1024) {
            return $size.' B';
        } elseif ($size < 1048576) {
            return round($size / 1024, 1).' KB';
        } else {
            return round($size / 1048576, 1).' MB';
        }
    }
}
