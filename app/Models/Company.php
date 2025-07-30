<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plan',
        'email_limit',
        'subscription_plan',
        'supported_languages',
        'escalation_settings',
        'business_hours',
        'follow_up_settings',
        'is_active',
    ];

    protected $casts = [
        'supported_languages' => 'array',
        'escalation_settings' => 'array',
        'business_hours' => 'array',
        'follow_up_settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function getCurrentMonthUsage(): int
    {
        return $this->emailAccounts()
            ->withCount(['emailMessages' => function ($query) {
                $query->whereMonth('received_at', now()->month)
                    ->whereYear('received_at', now()->year);
            }])
            ->get()
            ->sum('email_messages_count');
    }
}
