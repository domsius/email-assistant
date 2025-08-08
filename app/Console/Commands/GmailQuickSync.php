<?php

namespace App\Console\Commands;

use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use App\Services\GmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GmailQuickSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:quick-sync 
                            {--account= : Specific account ID to sync}
                            {--history : Use history-based sync if available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quick sync for Gmail accounts using history ID for efficiency';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->option('account');
        $useHistory = $this->option('history');
        
        if ($accountId) {
            $accounts = EmailAccount::where('id', $accountId)
                ->where('provider', 'gmail')
                ->where('is_active', true)
                ->get();
        } else {
            $accounts = EmailAccount::where('provider', 'gmail')
                ->where('is_active', true)
                ->get();
        }
        
        if ($accounts->isEmpty()) {
            $this->info('No active Gmail accounts found');
            return 0;
        }
        
        foreach ($accounts as $account) {
            try {
                // Check if we should sync (avoid too frequent syncs)
                if ($this->shouldSync($account)) {
                    $this->info("Quick syncing: {$account->email_address}");
                    
                    // Dispatch high-priority sync job
                    SyncEmailAccountJob::dispatch($account, [
                        'use_history' => $useHistory && $account->gmail_history_id,
                        'quick_sync' => true,
                    ])->onQueue('high-priority');
                    
                    // Update last sync time
                    $account->update(['last_sync_at' => now()]);
                } else {
                    $this->info("Skipping {$account->email_address} - synced recently");
                }
            } catch (\Exception $e) {
                $this->error("Failed to sync {$account->email_address}: {$e->getMessage()}");
                Log::error('Gmail quick sync failed', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return 0;
    }
    
    /**
     * Check if account should be synced
     * Avoid syncing if it was synced in the last 30 seconds
     */
    private function shouldSync(EmailAccount $account): bool
    {
        if (!$account->last_sync_at) {
            return true;
        }
        
        // Only sync if last sync was more than 30 seconds ago
        return $account->last_sync_at->lt(now()->subSeconds(30));
    }
}