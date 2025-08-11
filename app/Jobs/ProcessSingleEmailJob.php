<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Services\EmailProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSingleEmailJob implements ShouldQueue
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
    public $timeout = 120; // 2 minutes per email

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailAccount $emailAccount,
        public string $messageId,
        public array $options = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailProviderFactory $providerFactory): void
    {


        try {
            // Use a lock to prevent race conditions when multiple jobs process the same email
            $lockKey = "email_process_{$this->emailAccount->id}_{$this->messageId}";
            $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 30);
            
            if (!$lock->get()) {
                Log::info('Email is being processed by another job, skipping', [
                    'account_id' => $this->emailAccount->id,
                    'message_id' => $this->messageId,
                ]);
                return;
            }
            
            try {
                // Check if email already exists (inside the lock)
                $existingEmail = EmailMessage::where('message_id', $this->messageId)
                    ->where('email_account_id', $this->emailAccount->id)
                    ->first();

                if ($existingEmail) {
                    Log::info('Email already exists, skipping', [
                        'account_id' => $this->emailAccount->id,
                        'message_id' => $this->messageId,
                    ]);

                    return;
                }

            $provider = $providerFactory->createProvider($this->emailAccount);

            // Process the single email
            $emailData = $provider->processSingleEmail($this->messageId, $this->options);

            if (! $emailData) {
                throw new \Exception('Failed to fetch email data from provider');
            }

            // Debug logging to check what we're getting from Gmail
            Log::info('Email data from Gmail provider', [
                'message_id' => $this->messageId,
                'is_read' => $emailData['is_read'] ?? 'NOT SET',
                'is_important' => $emailData['is_important'] ?? 'NOT SET',
                'folder' => $emailData['folder'] ?? 'NOT SET',
                'labels' => $emailData['provider_data']['labels'] ?? [],
            ]);

            // Find or create customer
            $customer = Customer::firstOrCreate(
                [
                    'email' => $emailData['sender_email'],
                    'company_id' => $this->emailAccount->company_id,
                ],
                [
                    'name' => $emailData['sender_name'] ?? '',
                    'first_contact_at' => now(),
                    'journey_stage' => 'initial',
                ]
            );

            // Parse received date - use received_at from Gmail
            $receivedAt = isset($emailData['received_at'])
                ? (is_string($emailData['received_at']) 
                    ? \Carbon\Carbon::parse($emailData['received_at']) 
                    : $emailData['received_at'])
                : now();

            // Create email message
            $bodyContent = $emailData['body_content'] ?? $emailData['body_html'] ?? '';
            $bodyHtml = $emailData['body_html'] ?? null;
            $snippet = substr(strip_tags($bodyContent), 0, 150);

            try {
                $emailMessage = EmailMessage::create([
                    'email_account_id' => $this->emailAccount->id,
                    'customer_id' => $customer->id,
                    'message_id' => $this->messageId,
                    'thread_id' => $emailData['thread_id'] ?? null,
                    'folder' => $emailData['folder'] ?? 'INBOX',
                    'subject' => $emailData['subject'] ?? 'No Subject',
                    'body_content' => $bodyContent,
                    'body_plain' => strip_tags($bodyContent),
                    'body_html' => $bodyHtml,
                    'snippet' => $snippet,
                    'sender_email' => $emailData['sender_email'],
                    'from_email' => $emailData['sender_email'],
                    'sender_name' => $emailData['sender_name'] ?? '',
                    'received_at' => $receivedAt,
                    'status' => 'pending',
                    'processing_status' => 'pending',
                    'labels' => $emailData['provider_data']['labels'] ?? [],
                    'is_read' => $emailData['is_read'] ?? false,
                    'is_important' => $emailData['is_important'] ?? false,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Check if it's a unique constraint violation
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    Log::info('Email already exists (caught by unique constraint), skipping', [
                        'account_id' => $this->emailAccount->id,
                        'message_id' => $this->messageId,
                    ]);
                    return;
                }
                throw $e; // Re-throw if it's a different database error
            }

            Log::info('Successfully processed and saved email', [
                'account_id' => $this->emailAccount->id,
                'message_id' => $this->messageId,
                'email_id' => $emailMessage->id,
                'is_read_saved' => $emailMessage->is_read,
                'folder_saved' => $emailMessage->folder,
            ]);

            // Broadcast new email event for real-time updates
            try {
                Log::info('Broadcasting NewEmailReceived event', ['email_id' => $emailMessage->id]);
                broadcast(new \App\Events\NewEmailReceived($emailMessage));
                Log::info('Successfully broadcasted NewEmailReceived event', ['email_id' => $emailMessage->id]);
            } catch (\Exception $e) {
                Log::error('Failed to broadcast new email event', [
                    'email_id' => $emailMessage->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Dispatch AI processing job if enabled
            if (config('email-processing.auto_process_incoming', false)) {
                ProcessIncomingEmail::dispatch($emailMessage);
            }
            } finally {
                // Always release the lock
                $lock->release();
            }
        } catch (\Exception $e) {
            Log::error('Error processing single email', [
                'account_id' => $this->emailAccount->id,
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // 30 seconds, 1 minute, 2 minutes
    }
}
