<?php

namespace App\Jobs;

use App\Events\EmailsUpdated;
use App\Models\EmailAccount;
use App\Services\EmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEmailAccountJob implements ShouldQueue
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
    public $timeout = 1200; // 20 minutes

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailAccount $emailAccount,
        public array $options = []
    ) {
        $this->options = array_merge([
            'limit' => 25, // Reduced from 100 to prevent timeouts
            'batch_size' => 5, // Process 5 emails at a time
            'force' => false,
        ], $options);
    }

    /**
     * Execute the job.
     */
    public function handle(EmailSyncService $syncService): void
    {
        try {
            // Check if this is a webhook-triggered, quick, or manual sync after initial sync
            $isWebhookSync = $this->options['webhook_sync'] ?? false;
            $isQuickSync = $this->options['quick_sync'] ?? false;
            $isManualSync = $this->options['manual_sync'] ?? false;
            
            // If this is a webhook, quick, or manual sync, only fetch new emails (small batch)
            if ($isWebhookSync || $isQuickSync || $isManualSync) {
                $this->options['limit'] = $this->options['limit'] ?? 10; // Use provided limit or default to 10
                $this->options['fetch_all'] = $this->options['fetch_all'] ?? false; // Only unread/new emails by default
                
                Log::info('Limited sync for new emails only', [
                    'account_id' => $this->emailAccount->id,
                    'email' => $this->emailAccount->email_address,
                    'type' => $isWebhookSync ? 'webhook' : ($isQuickSync ? 'quick' : 'manual'),
                ]);
            }
            
            // Use optimized sync method
            $result = $syncService->syncEmailsOptimized($this->emailAccount, $this->options);

            if ($result['success']) {
                // Broadcast that emails have been updated
                event(new EmailsUpdated(
                    $this->emailAccount->company_id,
                    $this->emailAccount->id,
                    [
                        'processed' => $result['processed'] ?? 0,
                        'has_more' => $result['has_more'] ?? false,
                    ]
                ));

                // Only continue syncing for INITIAL sync (when explicitly marked)
                // Regular syncs, webhook syncs, and quick syncs should NOT paginate
                $isInitialSync = $this->options['initial_sync'] ?? false;
                
                if ($isInitialSync && ($result['has_more'] ?? false)) {
                    $newOptions = $this->options;
                    $newOptions['page_token'] = $result['next_page_token'] ?? null;

                    Log::info('Initial sync continuing with pagination', [
                        'account_id' => $this->emailAccount->id,
                        'next_page_token' => $result['next_page_token'] ?? null,
                    ]);

                    self::dispatch($this->emailAccount, $newOptions)
                        ->delay(now()->addSeconds(10)); // Small delay to avoid rate limits
                } else if (($isWebhookSync || $isQuickSync) && $result['processed'] > 0) {
                    Log::info('Limited sync completed', [
                        'account_id' => $this->emailAccount->id,
                        'processed' => $result['processed'],
                        'type' => $isWebhookSync ? 'webhook' : 'quick',
                    ]);
                }
            } else {
                Log::error('Email sync job failed', [
                    'account_id' => $this->emailAccount->id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'processed' => $result['processed'] ?? 0,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Email sync job exception', [
                'account_id' => $this->emailAccount->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 600]; // 1 minute, 5 minutes, 10 minutes
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Email sync job permanently failed', [
            'account_id' => $this->emailAccount->id,
            'error' => $exception->getMessage(),
        ]);

        // Update account status to indicate sync failure
        $this->emailAccount->update([
            'is_active' => false,
            'last_sync_at' => now(),
        ]);
    }
}
