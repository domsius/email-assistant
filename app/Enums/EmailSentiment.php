<?php

namespace App\Enums;

enum EmailSentiment: string
{
    case POSITIVE = 'positive';
    case NEGATIVE = 'negative';
    case NEUTRAL = 'neutral';

    public function getColor(): string
    {
        return match ($this) {
            self::POSITIVE => 'green',
            self::NEGATIVE => 'red',
            self::NEUTRAL => 'gray',
        };
    }
}
