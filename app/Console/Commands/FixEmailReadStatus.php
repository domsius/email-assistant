<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use Illuminate\Console\Command;

class FixEmailReadStatus extends Command
{
    protected $signature = 'emails:fix-read-status';
    protected $description = 'Fix read status for emails based on Gmail labels';

    public function handle()
    {
        $this->info('Fixing email read status based on Gmail labels...');
        
        // Fix emails that don't have UNREAD label but are marked as unread
        $updated = EmailMessage::where('is_read', false)
            ->whereJsonDoesntContain('labels', 'UNREAD')
            ->update(['is_read' => true]);
            
        $this->info("Updated $updated emails to read status");
        
        // Count remaining unread (these should have UNREAD label)
        $stillUnread = EmailMessage::where('is_read', false)->count();
        $this->info("Remaining unread emails: $stillUnread (these should have UNREAD label)");
        
        return Command::SUCCESS;
    }
}