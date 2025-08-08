<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'email_address',
        'provider',
        'provider_account_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'provider_settings',
        'oauth_state',
        'is_active',
        'sync_status',
        'sync_progress',
        'sync_total',
        'sync_error',
        'sync_started_at',
        'sync_completed_at',
        'last_sync_at',
        'gmail_watch_token',
        'gmail_watch_expiration',
        'gmail_history_id',
    ];

    protected $casts = [
        'provider_settings' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'sync_started_at' => 'datetime',
        'sync_completed_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean',
        'sync_progress' => 'integer',
        'sync_total' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(EmailAccountAlias::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function needsReauthentication(): bool
    {
        return ! $this->access_token || ! $this->refresh_token || ! $this->is_active;
    }

    /**
     * Get the provider settings as an array
     */
    public function getProviderSettingsAttribute($value)
    {
        if (! $value) {
            return [];
        }

        // The value is already decrypted by Laravel's 'encrypted' cast
        // Now we need to JSON decode it
        $decoded = json_decode($value, true);

        return $decoded ?: [];
    }

    /**
     * Set the provider settings from an array
     */
    public function setProviderSettingsAttribute($value)
    {
        if ($value === null || empty($value)) {
            $this->attributes['provider_settings'] = null;

            return;
        }

        // JSON encode the array before encryption (handled by Laravel's 'encrypted' cast)
        $this->attributes['provider_settings'] = json_encode($value);
    }
}
