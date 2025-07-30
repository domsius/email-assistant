<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
        'ai_model_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ai_model_config' => 'array',
    ];

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'detected_language', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getSupportedLanguages(): array
    {
        return self::active()->pluck('name', 'code')->toArray();
    }
}
