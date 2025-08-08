<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\GmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetupGmailWatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:setup-watches 
                            {--account= : Specific account ID to set up watch for}
                            {--renew : Renew expiring watches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up Gmail push notification watches for real-time email updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->option('account');
        $renewOnly = $this->option('renew');
        
        if ($accountId) {
            // Set up watch for specific account
            $account = EmailAccount::find($accountId);
            
            if (!$account || $account->provider !== 'gmail') {
                $this->error('Invalid Gmail account ID');
                return 1;
            }
            
            $this->setupWatchForAccount($account, $renewOnly);
        } else {
            // Set up watches for all Gmail accounts
            $accounts = EmailAccount::where('provider', 'gmail')
                ->where('is_active', true)
                ->get();
            
            $this->info("Found {$accounts->count()} active Gmail accounts");
            
            foreach ($accounts as $account) {
                $this->setupWatchForAccount($account, $renewOnly);
            }
        }
        
        $this->info('Gmail watch setup completed');
        return 0;
    }
    
    /**
     * Set up watch for a specific account
     */
    private function setupWatchForAccount(EmailAccount $account, bool $renewOnly = false): void
    {
        try {
            $this->info("Processing account: {$account->email_address}");
            
            $gmailService = new GmailService($account);
            
            if ($renewOnly) {
                // Only renew if needed
                if ($gmailService->watchNeedsRenewal()) {
                    $this->info("  → Watch needs renewal");
                    if ($gmailService->renewWatchIfNeeded()) {
                        $this->info("  ✓ Watch renewed successfully");
                    } else {
                        $this->error("  ✗ Failed to renew watch");
                    }
                } else {
                    $this->info("  → Watch still valid until {$account->gmail_watch_expiration}");
                }
            } else {
                // Set up new watch
                if ($gmailService->setupWatch()) {
                    $this->info("  ✓ Watch set up successfully");
                    $this->info("  → Expires: {$account->fresh()->gmail_watch_expiration}");
                } else {
                    $this->error("  ✗ Failed to set up watch");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("  ✗ Error: {$e->getMessage()}");
            Log::error('Failed to set up Gmail watch', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}