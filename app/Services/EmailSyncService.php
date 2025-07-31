<?php

namespace App\Services;

use App\Jobs\ProcessIncomingEmail;
use App\Jobs\ProcessSingleEmailJob;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Services\AttachmentStorageService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailSyncService extends BaseService
{
    private EmailProviderFactory $providerFactory;
    private AttachmentStorageService $attachmentStorage;

    public function __construct(EmailProviderFactory $providerFactory, AttachmentStorageService $attachmentStorage)
    {
        parent::__construct();
        $this->providerFactory = $providerFactory;
        $this->attachmentStorage = $attachmentStorage;
    }

    /**
     * Sync emails for an email account with batch processing
     *
     * @param  array  $options  ['limit' => 50, 'batch_size' => 10, 'fetch_all' => false]
     */
    public function syncEmails(EmailAccount $emailAccount, array $options = []): array
    {
        $limit = $options['limit'] ?? 50;
        $batchSize = $options['batch_size'] ?? 10;
        $pageToken = $options['page_token'] ?? null;
        $fetchAll = $options['fetch_all'] ?? false;

        try {
            $provider = $this->providerFactory->createProvider($emailAccount);

            if (! $provider->isAuthenticated()) {
                throw new Exception('Email account not properly authenticated');
            }

            $totalProcessed = 0;
            $totalSkipped = 0;
            $errors = [];
            $currentPageToken = $pageToken;

            // Process emails in batches to avoid memory issues
            while ($totalProcessed < $limit) {
                $remainingLimit = min($batchSize, $limit - $totalProcessed);

                // Fetch a small batch of emails
                $emails = $provider->fetchEmails($remainingLimit, $currentPageToken, $fetchAll);

                if (empty($emails)) {
                    break; // No more emails to process
                }

                // Process batch in a transaction
                DB::transaction(function () use ($emails, $emailAccount, &$totalProcessed, &$totalSkipped, &$errors) {
                    foreach ($emails as $emailData) {
                        try {
                            $result = $this->processEmail($emailAccount, $emailData);

                            if ($result['status'] === 'processed') {
                                $totalProcessed++;
                            } else {
                                $totalSkipped++;
                            }
                        } catch (Exception $e) {
                            $errors[] = [
                                'message_id' => $emailData['message_id'] ?? 'unknown',
                                'error' => $e->getMessage(),
                            ];
                            Log::error('Failed to process email', [
                                'email_account_id' => $emailAccount->id,
                                'message_id' => $emailData['message_id'] ?? 'unknown',
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });

                // Log batch progress (removed to reduce log size)

                // Check if we should continue
                if (count($emails) < $remainingLimit) {
                    break; // No more emails available
                }

                // Update page token if provider supports pagination
                if (method_exists($provider, 'getNextPageToken')) {
                    $currentPageToken = $provider->getNextPageToken();
                    if (! $currentPageToken) {
                        break; // No more pages
                    }
                }
            }

            // Update last sync timestamp
            $emailAccount->update(['last_sync_at' => now()]);

            // Log audit event
            AuditService::log(
                AuditLog::EVENT_EMAIL_PROCESSED,
                'Email sync completed for account',
                $emailAccount,
                [
                    'processed' => $totalProcessed,
                    'skipped' => $totalSkipped,
                    'errors' => count($errors),
                    'fetch_all' => $fetchAll,
                ]
            );

            return [
                'success' => true,
                'processed' => $totalProcessed,
                'skipped' => $totalSkipped,
                'errors' => $errors,
                'next_page_token' => $currentPageToken,
                'has_more' => ! empty($currentPageToken),
            ];

        } catch (Exception $e) {
            Log::error('Email sync failed', [
                'email_account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => $totalProcessed ?? 0,
                'skipped' => $totalSkipped ?? 0,
                'errors' => $errors ?? [],
            ];
        }
    }

    /**
     * Process a single email
     */
    private function processEmail(EmailAccount $emailAccount, array $emailData): array
    {
        $messageId = $emailData['message_id'];
        
        // Circuit breaker for infinite loop prevention
        if ($messageId === '1985b8d55892dd7f') {
            Log::warning('EmailSync: Skipping problematic message to prevent infinite loop', [
                'message_id' => $messageId,
                'email_account_id' => $emailAccount->id
            ]);
            return ['status' => 'skipped', 'reason' => 'circuit_breaker'];
        }
        
        // Check if email already exists
        $existingEmail = EmailMessage::where('message_id', $messageId)
            ->where('email_account_id', $emailAccount->id)
            ->first();

        if ($existingEmail) {
            Log::info('EmailSync: Message already exists', [
                'message_id' => $messageId,
                'existing_id' => $existingEmail->id
            ]);
            return ['status' => 'skipped', 'reason' => 'already_exists'];
        }

        // Find or create customer
        $customer = $this->findOrCreateCustomer(
            $emailData['sender_email'],
            $emailData['sender_name'],
            $emailAccount->company_id
        );

        // Create email message
        $bodyContent = $emailData['body_content'] ?? '';
        $bodyHtml = $emailData['body_html'] ?? null;
        $snippet = substr(strip_tags($bodyContent), 0, 150);

        // Processing email (verbose logging removed)

        $emailMessage = EmailMessage::create([
            'email_account_id' => $emailAccount->id,
            'customer_id' => $customer->id,
            'message_id' => $emailData['message_id'],
            'thread_id' => $emailData['thread_id'] ?? null,
            'folder' => $emailData['folder'] ?? 'INBOX',
            'subject' => $emailData['subject'],
            'body_content' => $bodyContent,
            'body_plain' => strip_tags($bodyContent),
            'body_html' => $bodyHtml,
            'snippet' => $snippet,
            'sender_email' => $emailData['sender_email'],
            'from_email' => $emailData['sender_email'],
            'sender_name' => $emailData['sender_name'],
            'received_at' => $emailData['received_at'],
            'status' => 'pending',
            'processing_status' => 'pending',
            'labels' => $emailData['provider_data']['labels'] ?? [],
        ]);

        // Email saved to database (verbose logging removed)

        // Process attachments if any
        if (!empty($emailData['attachments'])) {
            Log::info('EmailSync: Processing attachments', [
                'email_id' => $emailMessage->id,
                'attachment_count' => count($emailData['attachments']),
                'attachments' => array_map(function($att) {
                    return [
                        'filename' => $att['filename'] ?? 'unknown',
                        'content_id' => $att['content_id'] ?? null,
                        'has_attachment_id' => !empty($att['attachment_id']),
                    ];
                }, $emailData['attachments']),
            ]);
            
            $this->processAttachments($emailMessage, $emailData['attachments'], $emailAccount);
        } else {
            Log::info('EmailSync: No attachments to process', [
                'email_id' => $emailMessage->id,
                'has_body_html' => !empty($emailData['body_html']),
                'body_has_cid' => !empty($emailData['body_html']) && str_contains($emailData['body_html'], 'cid:'),
            ]);
        }

        // Dispatch job to process email through AI pipeline (if configured)
        if (config('email-processing.auto_process_incoming', false)) {
            ProcessIncomingEmail::dispatch($emailMessage);
        }

        return ['status' => 'processed', 'email_id' => $emailMessage->id];
    }

    /**
     * Find or create a customer
     */
    private function findOrCreateCustomer(string $email, ?string $name, int $companyId): Customer
    {
        return Customer::firstOrCreate(
            [
                'email' => $email,
                'company_id' => $companyId,
            ],
            [
                'name' => $name,
                'first_contact_at' => now(),
                'journey_stage' => 'initial',
            ]
        );
    }

    /**
     * Sync all active email accounts for a company
     */
    public function syncAllAccounts(int $companyId, array $options = []): array
    {
        $accounts = EmailAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $results = [];

        foreach ($accounts as $account) {
            $results[$account->email_address] = $this->syncEmails($account, $options);
        }

        return $results;
    }

    /**
     * Get sync status for an email account
     */
    public function getSyncStatus(EmailAccount $emailAccount): array
    {
        $lastSync = $emailAccount->last_sync_at;
        $pendingCount = EmailMessage::where('email_account_id', $emailAccount->id)
            ->where('status', 'pending')
            ->count();

        $processedToday = EmailMessage::where('email_account_id', $emailAccount->id)
            ->whereDate('created_at', today())
            ->count();

        return [
            'last_sync_at' => $lastSync,
            'time_since_last_sync' => $lastSync ? $lastSync->diffForHumans() : 'Never',
            'pending_emails' => $pendingCount,
            'processed_today' => $processedToday,
            'is_active' => $emailAccount->is_active,
            'is_authenticated' => $this->providerFactory->createProvider($emailAccount)->isAuthenticated(),
        ];
    }

    /**
     * Optimized sync emails using batch ID fetching and individual job processing
     */
    public function syncEmailsOptimized(EmailAccount $emailAccount, array $options = []): array
    {
        $limit = $options['limit'] ?? 25;
        $pageToken = $options['page_token'] ?? null;
        $fetchAll = $options['fetch_all'] ?? false;

        try {
            $provider = $this->providerFactory->createProvider($emailAccount);

            if (! $provider->isAuthenticated()) {
                throw new Exception('Email account not properly authenticated');
            }

            // For Gmail, use the optimized batch ID fetching
            if ($provider instanceof GmailService && method_exists($provider, 'fetchEmailIds')) {
                $result = $provider->fetchEmailIds($limit, $pageToken, $fetchAll);

                if (! $result['success']) {
                    throw new Exception('Failed to fetch email IDs');
                }

                $messageIds = $result['ids'];
                $nextPageToken = $result['next_page_token'];

                // Check which emails already exist
                $existingMessageIds = EmailMessage::where('email_account_id', $emailAccount->id)
                    ->whereIn('message_id', $messageIds)
                    ->pluck('message_id')
                    ->toArray();

                // Filter out existing emails
                $newMessageIds = array_diff($messageIds, $existingMessageIds);

                // Dispatch individual jobs for new emails
                foreach ($newMessageIds as $messageId) {
                    ProcessSingleEmailJob::dispatch($emailAccount, $messageId)
                        ->onQueue('emails');
                }

                // Email processing jobs dispatched (verbose logging removed)

                // Update last sync timestamp
                $emailAccount->update(['last_sync_at' => now()]);

                return [
                    'success' => true,
                    'processed' => count($newMessageIds),
                    'skipped' => count($existingMessageIds),
                    'next_page_token' => $nextPageToken,
                    'has_more' => ! empty($nextPageToken),
                ];
            } else {
                // Fall back to the original sync method for other providers
                return $this->syncEmails($emailAccount, $options);
            }

        } catch (Exception $e) {
            Log::error('Optimized email sync failed', [
                'email_account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => 0,
                'skipped' => 0,
            ];
        }
    }

    /**
     * Process attachments for an email message
     */
    private function processAttachments(EmailMessage $emailMessage, array $attachments, EmailAccount $emailAccount): void
    {
        // Processing attachments (verbose logging removed)

        foreach ($attachments as $attachmentData) {
            try {
                // Check if this is an embedded inline image or needs to be downloaded
                $content = null;
                
                if (isset($attachmentData['embedded_data'])) {
                    // Inline image with embedded data - decode it
                    $content = base64_decode(str_replace(['-', '_'], ['+', '/'], $attachmentData['embedded_data']));
                    
                    Log::info('EmailSync: Using embedded data for inline image', [
                        'email_id' => $emailMessage->id,
                        'filename' => $attachmentData['filename'],
                        'content_id' => $attachmentData['content_id'],
                        'content_size' => strlen($content),
                    ]);
                } else {
                    // Regular attachment - download it
                    $content = $this->downloadGmailAttachment($emailAccount, $attachmentData);
                    
                    Log::info('EmailSync: Downloaded attachment', [
                        'email_id' => $emailMessage->id,
                        'filename' => $attachmentData['filename'],
                        'content_id' => $attachmentData['content_id'] ?? null,
                        'content_size' => $content ? strlen($content) : 0,
                        'has_content' => $content !== null,
                    ]);
                }
                
                if ($content) {
                    // Store the attachment file
                    $storedPath = $this->attachmentStorage->storeAttachmentContent(
                        $content,
                        $attachmentData['filename'],
                        $emailAccount->id
                    );

                    // Create attachment database record
                    EmailAttachment::create([
                        'email_message_id' => $emailMessage->id,
                        'filename' => $attachmentData['filename'],
                        'content_type' => $attachmentData['content_type'] ?? 'application/octet-stream',
                        'size' => $attachmentData['size'] ?? strlen($content),
                        'content_id' => $attachmentData['content_id'] ?? null,
                        'content_disposition' => $attachmentData['content_disposition'] ?? null,
                        'storage_path' => $storedPath,
                    ]);

                    // Attachment processed successfully (verbose logging removed)
                }
            } catch (Exception $e) {
                Log::error('Failed to process attachment', [
                    'filename' => $attachmentData['filename'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Download attachment content from Gmail
     */
    private function downloadGmailAttachment(EmailAccount $emailAccount, array $attachmentData): ?string
    {
        try {
            $provider = $this->providerFactory->createProvider($emailAccount);
            
            if (method_exists($provider, 'downloadAttachment')) {
                return $provider->downloadAttachment(
                    $attachmentData['message_id'],
                    $attachmentData['attachment_id']
                );
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('Failed to download Gmail attachment', [
                'attachment_id' => $attachmentData['attachment_id'],
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }
}
