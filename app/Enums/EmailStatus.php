<?php

namespace App\Enums;

enum EmailStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'alert-circle',
            self::PROCESSING => 'clock',
            self::PROCESSED => 'check-circle',
            self::FAILED => 'x-circle',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'orange',
            self::PROCESSING => 'blue',
            self::PROCESSED => 'green',
            self::FAILED => 'red',
        };
    }
}
