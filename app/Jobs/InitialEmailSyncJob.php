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
            'max_limit' => $this->maxInitialSync,
        ]);

        try {
            // Mark sync as started
            $this->emailAccount->update([
                'sync_status' => 'syncing',
                'sync_progress' => 0,
                'sync_total' => $this->maxInitialSync, // Set to exactly 200
                'sync_error' => null,
                'sync_started_at' => now(),
                'sync_completed_at' => null,
            ]);

            // Sync emails in batches with progress updates
            $processedTotal = 0;
            $skippedTotal = 0;
            $batchSize = 50;
            $pageToken = null;
            $iterations = 0;

            // Continue until we've processed 200 emails or run out of emails
            while ($processedTotal < $this->maxInitialSync) {
                $iterations++;
                
                // Calculate how many emails we can still process
                $remainingToSync = $this->maxInitialSync - $processedTotal;
                $currentBatchLimit = min($batchSize, $remainingToSync);
                
                Log::info('Fetching email batch', [
                    'iteration' => $iterations,
                    'batch_size' => $currentBatchLimit,
                    'processed_so_far' => $processedTotal,
                    'remaining' => $remainingToSync,
                ]);
                
                $result = $syncService->syncEmailsOptimized($this->emailAccount, [
                    'limit' => $currentBatchLimit,
                    'page_token' => $pageToken,
                    'fetch_all' => true, // Include both read and unread emails
                    'max_total' => $this->maxInitialSync,
                    'already_processed' => $processedTotal,
                    'initial_sync' => true, // Mark this as initial sync for proper pagination
                ]);

                if ($result['success']) {
                    $processedTotal += $result['processed'];
                    $skippedTotal += $result['skipped'] ?? 0;

                    // Update progress
                    $this->emailAccount->update([
                        'sync_progress' => $processedTotal,
                    ]);

                    Log::info('Batch processed', [
                        'iteration' => $iterations,
                        'batch_processed' => $result['processed'],
                        'batch_skipped' => $result['skipped'] ?? 0,
                        'total_processed' => $processedTotal,
                        'total_skipped' => $skippedTotal,
                    ]);

                    // Check if we have more emails to fetch
                    $pageToken = $result['next_page_token'] ?? null;
                    
                    // Stop if no more emails available or we've reached our limit
                    if (!$pageToken || $processedTotal >= $this->maxInitialSync) {
                        break;
                    }

                    // Small delay to avoid rate limits
                    sleep(1);
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
                'total_skipped' => $skippedTotal,
                'iterations' => $iterations,
            ]);

            // Set up Gmail watch for real-time updates after initial sync
            if ($this->emailAccount->provider === 'gmail') {
                try {
                    $provider = $providerFactory->createProvider($this->emailAccount);
                    if (method_exists($provider, 'setupWatch')) {
                        $watchSuccess = $provider->setupWatch();
                        Log::info('Gmail watch setup ' . ($watchSuccess ? 'successful' : 'failed'), [
                            'account_id' => $this->emailAccount->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to set up Gmail watch after initial sync', [
                        'account_id' => $this->emailAccount->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }



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
            'sync_error' => 'Sync failed permanently: '.$exception->getMessage(),
            'sync_completed_at' => now(),
        ]);
    }
}
