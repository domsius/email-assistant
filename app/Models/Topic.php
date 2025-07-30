<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'color',
        'priority',
        'keywords',
        'priority_weight',
        'estimated_response_hours',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get emails associated with this topic
     */
    public function emails(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    /**
     * Scope for active topics
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get priority order for sorting
     */
    public function getPriorityOrderAttribute(): int
    {
        $priorities = [
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
        ];

        return $priorities[$this->priority] ?? 5;
    }
}
