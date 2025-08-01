<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAccountAlias extends Model
{
    protected $fillable = [
        'email_account_id',
        'email_address',
        'name',
        'is_default',
        'is_verified',
        'reply_to_address',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        'settings' => 'array',
    ];

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * Get the display name for the alias
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return "{$this->name} <{$this->email_address}>";
        }
        
        return $this->email_address;
    }
}
