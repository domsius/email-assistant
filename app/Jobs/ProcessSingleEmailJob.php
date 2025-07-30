<?php

namespace App\Jobs;

use App\Jobs\ProcessIncomingEmail;
use App\Models\EmailAccount;
use App\Models\Customer;
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
        Log::info('Processing single email', [
            'account_id' => $this->emailAccount->id,
            'message_id' => $this->messageId,
        ]);

        try {
            // Check if email already exists
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
            
            if (!$emailData) {
                throw new \Exception('Failed to fetch email data from provider');
            }
            
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
            
            // Parse received date
            $receivedAt = isset($emailData['date']) 
                ? \Carbon\Carbon::parse($emailData['date']) 
                : now();
            
            // Create email message
            $bodyContent = $emailData['body_content'] ?? $emailData['body_html'] ?? '';
            $bodyHtml = $emailData['body_html'] ?? null;
            $snippet = substr(strip_tags($bodyContent), 0, 150);
            
            $emailMessage = EmailMessage::create([
                'email_account_id' => $this->emailAccount->id,
                'customer_id' => $customer->id,
                'message_id' => $this->messageId,
                'thread_id' => $emailData['thread_id'] ?? null,
                'folder' => 'INBOX',
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
                'labels' => $emailData['labels'] ?? [],
            ]);
            
            Log::info('Successfully processed and saved email', [
                'account_id' => $this->emailAccount->id,
                'message_id' => $this->messageId,
                'email_id' => $emailMessage->id,
            ]);
            
            // Dispatch AI processing job if enabled
            if (config('email-processing.auto_process_incoming', false)) {
                ProcessIncomingEmail::dispatch($emailMessage);
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