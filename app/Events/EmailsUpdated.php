<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $companyId,
        public ?int $accountId = null,
        public array $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->companyId.'.emails'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'emails.updated';
    }

    public function broadcastWith(): array
    {
        return array_merge([
            'account_id' => $this->accountId,
            'timestamp' => now()->toIso8601String(),
        ], $this->sanitizeData($this->data));
    }

    private function sanitizeData(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                // Ensure UTF-8 encoding and remove invalid characters
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                // Remove non-printable characters except newlines and tabs
                return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
            }
            if (is_array($value)) {
                return $this->sanitizeData($value);
            }
            return $value;
        }, $data);
    }
}
