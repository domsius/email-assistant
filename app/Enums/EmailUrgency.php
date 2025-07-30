<?php

namespace App\Enums;

enum EmailUrgency: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 4 => self::HIGH,
            $score >= 3 => self::MEDIUM,
            default => self::LOW,
        };
    }

    public function getBadgeVariant(): string
    {
        return match ($this) {
            self::HIGH => 'destructive',
            self::MEDIUM => 'secondary',
            self::LOW => 'outline',
        };
    }
}
