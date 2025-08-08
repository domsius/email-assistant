<?php

namespace App\Events;

use App\Models\EmailMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewEmailReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public EmailMessage $email;
    public int $companyId;

    public function __construct(EmailMessage $email)
    {
        $this->email = $email;
        $this->companyId = $email->emailAccount->company_id;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('company.' . $this->companyId . '.emails');
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->email->id,
            'subject' => $this->sanitizeForBroadcast($this->email->subject),
            'sender_email' => $this->sanitizeForBroadcast($this->email->sender_email),
            'sender_name' => $this->sanitizeForBroadcast($this->email->sender_name),
            'snippet' => $this->sanitizeForBroadcast($this->email->snippet),
            'received_at' => $this->email->received_at,
            'folder' => $this->email->folder,
            'is_read' => $this->email->is_read,
            'has_attachments' => $this->email->attachments()->exists(),
        ];
    }

    private function sanitizeForBroadcast($value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Ensure UTF-8 encoding and remove invalid characters
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        
        // Remove non-printable characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        
        return $value;
    }

    public function broadcastAs(): string
    {
        return 'email.received';
    }
}