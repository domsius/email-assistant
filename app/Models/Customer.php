<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'email',
        'name',
        'phone',
        'preferred_language',
        'category',
        'communication_preferences',
        'first_contact_at',
        'last_interaction_at',
        'total_interactions',
        'total_follow_ups_sent',
        'satisfaction_score',
        'journey_stage',
        'notes',
    ];

    protected $casts = [
        'communication_preferences' => 'array',
        'first_contact_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'satisfaction_score' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function communicationTimelines(): HasMany
    {
        return $this->hasMany(CommunicationTimeline::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('journey_stage', ['churned']);
    }

    public function updateLastInteraction()
    {
        $this->update([
            'last_interaction_at' => now(),
            'total_interactions' => $this->total_interactions + 1,
        ]);
    }
}
