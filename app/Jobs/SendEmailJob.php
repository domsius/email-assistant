<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Models\EmailDraft;
use App\Models\EmailMessage;
use App\Services\EmailProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEmailJob implements ShouldQueue
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
    public $timeout = 120; // 2 minutes

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
        public array $emailData,
        public ?int $draftId = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(EmailProviderFactory $providerFactory): void
    {
        Log::info('Starting email send job', [
            'account_id' => $this->emailAccount->id,
            'email' => $this->emailAccount->email_address,
            'to' => $this->emailData['to'],
            'subject' => $this->emailData['subject'],
        ]);

        try {
            // Create the email provider instance
            $provider = $providerFactory->createProvider($this->emailAccount);

            // Ensure the account is authenticated
            if (!$provider->isAuthenticated()) {
                // Try to refresh the token
                if (!$provider->refreshToken()) {
                    throw new \Exception('Failed to authenticate email account');
                }
            }

            // Prepare email data for the provider
            $providerEmailData = [
                'to' => implode(', ', $this->emailData['to']),
                'cc' => !empty($this->emailData['cc']) ? implode(', ', $this->emailData['cc']) : null,
                'bcc' => !empty($this->emailData['bcc']) ? implode(', ', $this->emailData['bcc']) : null,
                'subject' => $this->emailData['subject'],
                'body' => $this->emailData['body'],
                'in_reply_to' => $this->emailData['inReplyTo'] ?? null,
                'references' => $this->emailData['references'] ?? null,
            ];

            // Send the email through the provider
            $sent = $provider->sendEmail($providerEmailData);

            if (!$sent) {
                throw new \Exception('Failed to send email through provider');
            }

            // Store the sent email in the database
            $this->storeSentEmail();

            // Delete the draft if applicable
            if ($this->draftId) {
                $this->deleteDraft();
            }

            Log::info('Email sent successfully', [
                'account_id' => $this->emailAccount->id,
                'to' => $this->emailData['to'],
                'subject' => $this->emailData['subject'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'account_id' => $this->emailAccount->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow to trigger retry
            throw $e;
        }
    }

    /**
     * Store the sent email in the database
     */
    private function storeSentEmail(): void
    {
        $emailMessage = new EmailMessage();
        $emailMessage->email_account_id = $this->emailAccount->id;
        $emailMessage->message_id = uniqid('sent_', true) . '@' . $this->emailAccount->email_address;
        $emailMessage->folder = 'sent';
        $emailMessage->subject = $this->emailData['subject'];
        $emailMessage->body_plain = strip_tags($this->emailData['body']);
        $emailMessage->body_html = $this->emailData['body'];
        $emailMessage->from_email = $this->emailAccount->email_address;
        $emailMessage->sender_email = $this->emailAccount->email_address;
        $emailMessage->sender_name = $this->emailAccount->display_name;
        $emailMessage->received_at = now();
        $emailMessage->is_read = true;
        $emailMessage->processing_status = 'completed';
        
        // Store recipients in JSON format
        $emailMessage->to_recipients = json_encode($this->emailData['to']);
        if (!empty($this->emailData['cc'])) {
            $emailMessage->cc_recipients = json_encode($this->emailData['cc']);
        }
        if (!empty($this->emailData['bcc'])) {
            $emailMessage->bcc_recipients = json_encode($this->emailData['bcc']);
        }
        
        // Store thread information if replying
        if (!empty($this->emailData['inReplyTo'])) {
            $emailMessage->in_reply_to = $this->emailData['inReplyTo'];
        }
        if (!empty($this->emailData['references'])) {
            $emailMessage->references = $this->emailData['references'];
        }
        
        // Link to original email if replying/forwarding
        if (!empty($this->emailData['originalEmailId'])) {
            $originalEmail = EmailMessage::find($this->emailData['originalEmailId']);
            if ($originalEmail && $originalEmail->thread_id) {
                $emailMessage->thread_id = $originalEmail->thread_id;
            }
        }
        
        $emailMessage->save();
    }

    /**
     * Delete the draft if one was used
     */
    private function deleteDraft(): void
    {
        try {
            $draft = EmailDraft::find($this->draftId);
            if ($draft) {
                $draft->delete();
                Log::info('Draft deleted after sending', ['draft_id' => $this->draftId]);
            }
        } catch (\Exception $e) {
            // Log but don't fail the job if draft deletion fails
            Log::warning('Failed to delete draft after sending', [
                'draft_id' => $this->draftId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Email send job failed permanently', [
            'account_id' => $this->emailAccount->id,
            'to' => $this->emailData['to'],
            'subject' => $this->emailData['subject'],
            'error' => $exception->getMessage(),
        ]);

        // Here you could implement additional failure handling:
        // - Send notification to user
        // - Create audit log entry
        // - Update draft status to 'failed'
    }
}