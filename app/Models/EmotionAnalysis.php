<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmotionAnalysis extends Model
{
    protected $table = 'emotion_analyses';

    protected $fillable = [
        'email_message_id',
        'sentiment',
        'emotion',
        'confidence_score',
        'analysis_data',
    ];

    protected $casts = [
        'analysis_data' => 'array',
        'confidence_score' => 'decimal:2',
    ];

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }
}
