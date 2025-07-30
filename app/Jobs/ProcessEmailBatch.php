<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Services\EmailSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEmailBatch implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailAccount $emailAccount,
        public array $options = []
    ) {
        // Set default options
        $this->options = array_merge([
            'limit' => 100,
            'batch_size' => 20,
        ], $options);
    }

    /**
     * Execute the job.
     */
    public function handle(EmailSyncService $syncService): void
    {
        Log::info('Starting batch email processing', [
            'email_account_id' => $this->emailAccount->id,
            'email_address' => $this->emailAccount->email_address,
            'options' => $this->options,
        ]);

        try {
            // Check if account is still active
            if (! $this->emailAccount->is_active) {
                Log::warning('Email account is not active, skipping batch processing', [
                    'email_account_id' => $this->emailAccount->id,
                ]);

                return;
            }

            // Process emails in batches
            $result = $syncService->syncEmails($this->emailAccount, $this->options);

            if ($result['success']) {
                Log::info('Batch email processing completed', [
                    'email_account_id' => $this->emailAccount->id,
                    'processed' => $result['processed'],
                    'skipped' => $result['skipped'],
                    'errors' => count($result['errors']),
                    'has_more' => $result['has_more'],
                ]);

                // If there are more emails to process, dispatch another job
                if ($result['has_more'] && $result['next_page_token']) {
                    $newOptions = $this->options;
                    $newOptions['page_token'] = $result['next_page_token'];

                    ProcessEmailBatch::dispatch($this->emailAccount, $newOptions)
                        ->delay(now()->addSeconds(10)); // Add small delay to avoid rate limiting

                    Log::info('Dispatched continuation job for remaining emails', [
                        'email_account_id' => $this->emailAccount->id,
                        'page_token' => $result['next_page_token'],
                    ]);
                }
            } else {
                Log::error('Batch email processing failed', [
                    'email_account_id' => $this->emailAccount->id,
                    'error' => $result['error'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Batch email processing exception', [
                'email_account_id' => $this->emailAccount->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't retry, but log the failure
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch email processing job failed', [
            'email_account_id' => $this->emailAccount->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
