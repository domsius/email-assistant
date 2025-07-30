<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Services\EmailProviderFactory;
use App\Services\EmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InitialEmailSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 1800; // 30 minutes
    
    /**
     * Maximum emails to sync in initial sync
     *
     * @var int
     */
    private $maxInitialSync;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailAccount $emailAccount
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailSyncService $syncService, EmailProviderFactory $providerFactory): void
    {
        // Set max initial sync from config
        $this->maxInitialSync = config('mail-sync.sync_email_limit', 200);
        Log::info('Starting initial email sync', [
            'account_id' => $this->emailAccount->id,
            'email' => $this->emailAccount->email_address,
        ]);

        try {
            // Mark sync as started
            $this->emailAccount->update([
                'sync_status' => 'syncing',
                'sync_progress' => 0,
                'sync_total' => 0,
                'sync_error' => null,
                'sync_started_at' => now(),
                'sync_completed_at' => null,
            ]);

            // First, get total email count from provider
            $provider = $providerFactory->createProvider($this->emailAccount);
            $accountInfo = $provider->getAccountInfo();
            $totalMessages = $accountInfo['messages_total'] ?? 0;
            
            // Limit initial sync to reasonable amount
            $messagesToSync = min($totalMessages, $this->maxInitialSync);

            // Update total count
            $this->emailAccount->update([
                'sync_total' => $messagesToSync,
            ]);

            Log::info('Total messages to sync', [
                'account_id' => $this->emailAccount->id,
                'total_in_account' => $totalMessages,
                'will_sync' => $messagesToSync,
            ]);

            // Sync emails in batches with progress updates
            $processedTotal = 0;
            $batchSize = 50;
            $pageToken = null;
            $hasMore = true;

            while ($hasMore && $processedTotal < $messagesToSync) {
                $result = $syncService->syncEmails($this->emailAccount, [
                    'limit' => $batchSize,
                    'batch_size' => 10,
                    'page_token' => $pageToken,
                    'fetch_all' => true, // Fetch all emails, not just unread
                ]);

                if ($result['success']) {
                    $processedTotal += $result['processed'];
                    
                    // Update progress
                    $this->emailAccount->update([
                        'sync_progress' => $processedTotal,
                    ]);

                    Log::info('Sync progress update', [
                        'account_id' => $this->emailAccount->id,
                        'processed' => $processedTotal,
                        'total' => $messagesToSync,
                        'percentage' => $messagesToSync > 0 ? round(($processedTotal / $messagesToSync) * 100, 2) : 0,
                    ]);

                    $hasMore = $result['has_more'] ?? false;
                    $pageToken = $result['next_page_token'] ?? null;

                    // Small delay to avoid rate limits
                    if ($hasMore) {
                        sleep(1);
                    }
                } else {
                    throw new \Exception($result['error'] ?? 'Unknown sync error');
                }
            }

            // Mark sync as completed
            $this->emailAccount->update([
                'sync_status' => 'completed',
                'sync_progress' => $processedTotal,
                'sync_completed_at' => now(),
                'last_sync_at' => now(),
            ]);

            Log::info('Initial email sync completed', [
                'account_id' => $this->emailAccount->id,
                'total_processed' => $processedTotal,
                'note' => $totalMessages > $this->maxInitialSync 
                    ? "Limited to {$this->maxInitialSync} most recent emails. Total account has {$totalMessages} emails."
                    : "All emails synced",
            ]);

        } catch (\Exception $e) {
            Log::error('Initial email sync failed', [
                'account_id' => $this->emailAccount->id,
                'error' => $e->getMessage(),
            ]);

            // Mark sync as failed
            $this->emailAccount->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'sync_completed_at' => now(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Initial email sync job permanently failed', [
            'account_id' => $this->emailAccount->id,
            'error' => $exception->getMessage(),
        ]);

        // Update account status to indicate sync failure
        $this->emailAccount->update([
            'sync_status' => 'failed',
            'sync_error' => 'Sync failed permanently: ' . $exception->getMessage(),
            'sync_completed_at' => now(),
        ]);
    }
}