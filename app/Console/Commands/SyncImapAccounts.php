<?php

namespace App\Console\Commands;

use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncImapAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:sync 
                            {--account=* : Specific account IDs to sync}
                            {--all : Sync all IMAP accounts}
                            {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync IMAP accounts with intelligent scheduling';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accounts = $this->getAccounts();
        
        if ($accounts->isEmpty()) {
            $this->info('No IMAP accounts to sync.');
            return 0;
        }

        $this->info("Syncing {$accounts->count()} IMAP account(s)");
        
        foreach ($accounts as $account) {
            // Skip if recently synced (unless forced)
            if (!$this->option('force') && $this->wasRecentlySynced($account)) {
                $this->info("Skipping {$account->email_address} - recently synced");
                continue;
            }
            
            $this->info("Dispatching sync for {$account->email_address}");
            
            // Dispatch sync job
            SyncEmailAccountJob::dispatch($account, [
                'quick_sync' => true,
                'limit' => 10, // Small batch for frequent polling
                'fetch_all' => false, // Only new emails
            ]);
            
            // Update last sync attempt
            $account->update(['last_sync_at' => now()]);
        }
        
        return 0;
    }

    private function getAccounts()
    {
        $query = EmailAccount::where('provider', 'imap')
            ->where('is_active', true);

        if ($this->option('all')) {
            return $query->get();
        }

        $accountIds = $this->option('account');
        if (!empty($accountIds)) {
            return $query->whereIn('id', $accountIds)->get();
        }

        // Default to accounts that need syncing
        return $query->where(function ($q) {
            $q->whereNull('last_sync_at')
              ->orWhere('last_sync_at', '<', Carbon::now()->subMinutes(5));
        })->get();
    }

    private function wasRecentlySynced(EmailAccount $account): bool
    {
        if (!$account->last_sync_at) {
            return false;
        }
        
        // Consider "recently synced" if synced within the last 2 minutes
        return $account->last_sync_at->isAfter(Carbon::now()->subMinutes(2));
    }
}