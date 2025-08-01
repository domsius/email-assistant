<?php

namespace App\DTOs;

class FolderCountsDTO
{
    public function __construct(
        public readonly int $inbox = 0,
        public readonly int $drafts = 0,
        public readonly int $sent = 0,
        public readonly int $junk = 0,
        public readonly int $trash = 0,
        public readonly int $archive = 0,
        public readonly int $unread = 0,
        public readonly int $everything = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'inbox' => $this->inbox,
            'drafts' => $this->drafts,
            'sent' => $this->sent,
            'junk' => $this->junk,
            'trash' => $this->trash,
            'archive' => $this->archive,
            'unread' => $this->unread,
            'everything' => $this->everything,
        ];
    }
}
