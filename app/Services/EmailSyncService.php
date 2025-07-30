<?php

namespace App\Services;

use App\Jobs\ProcessIncomingEmail;
use App\Jobs\ProcessSingleEmailJob;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailSyncService extends BaseService
{
    private EmailProviderFactory $providerFactory;

    public function __construct(EmailProviderFactory $providerFactory)
    {
        parent::__construct();
        $this->providerFactory = $providerFactory;
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

                // Log batch progress
                Log::info('Email sync batch processed', [
                    'email_account_id' => $emailAccount->id,
                    'batch_size' => count($emails),
                    'total_processed' => $totalProcessed,
                    'total_skipped' => $totalSkipped,
                    'fetch_all' => $fetchAll,
                ]);

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
        // Check if email already exists
        $existingEmail = EmailMessage::where('message_id', $emailData['message_id'])
            ->where('email_account_id', $emailAccount->id)
            ->first();

        if ($existingEmail) {
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
        
        Log::info('=== EmailSync: Processing email for storage ===', [
            'message_id' => $emailData['message_id'],
            'subject' => $emailData['subject'],
            'has_body_content' => !empty($bodyContent),
            'body_content_length' => strlen($bodyContent),
            'has_body_html' => !empty($bodyHtml),
            'body_html_length' => strlen($bodyHtml ?? ''),
            'html_has_styles' => str_contains($bodyHtml ?? '', '<style'),
        ]);

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
        
        // Log what was actually saved
        Log::info('=== EmailSync: Email saved to database ===', [
            'email_id' => $emailMessage->id,
            'has_body_content' => !empty($emailMessage->body_content),
            'body_content_length' => strlen($emailMessage->body_content ?? ''),
            'has_body_html' => !empty($emailMessage->body_html),
            'body_html_length' => strlen($emailMessage->body_html ?? ''),
            'has_body_plain' => !empty($emailMessage->body_plain),
        ]);

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
                
                if (!$result['success']) {
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
                
                Log::info('Dispatched email processing jobs', [
                    'email_account_id' => $emailAccount->id,
                    'total_fetched' => count($messageIds),
                    'new_emails' => count($newMessageIds),
                    'skipped' => count($existingMessageIds),
                ]);
                
                // Update last sync timestamp
                $emailAccount->update(['last_sync_at' => now()]);
                
                return [
                    'success' => true,
                    'processed' => count($newMessageIds),
                    'skipped' => count($existingMessageIds),
                    'next_page_token' => $nextPageToken,
                    'has_more' => !empty($nextPageToken),
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
}
