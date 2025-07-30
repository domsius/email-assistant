<?php

namespace App\Enums;

enum EmailFolder: string
{
    case INBOX = 'INBOX';
    case SENT = 'SENT';
    case DRAFTS = 'DRAFTS';
    case SPAM = 'SPAM';
    case TRASH = 'TRASH';
    case ARCHIVE = 'ARCHIVE';

    public function label(): string
    {
        return match ($this) {
            self::INBOX => 'Inbox',
            self::SENT => 'Sent',
            self::DRAFTS => 'Drafts',
            self::SPAM => 'Junk',
            self::TRASH => 'Trash',
            self::ARCHIVE => 'Archive',
        };
    }

    public function isSystemFolder(): bool
    {
        return in_array($this, [
            self::INBOX,
            self::SENT,
            self::DRAFTS,
            self::SPAM,
            self::TRASH,
        ]);
    }
}
