<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Services\EmailProviderFactory;
use App\Services\LanguageDetectionService;
use Illuminate\Console\Command;

class SyncEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:sync {--account= : Specific account ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync emails from connected email accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->option('account');

        if ($accountId) {
            $accounts = EmailAccount::where('id', $accountId)->where('is_active', true)->get();
        } else {
            $accounts = EmailAccount::where('is_active', true)->get();
        }

        if ($accounts->isEmpty()) {
            $this->error('No active email accounts found to sync.');

            return 1;
        }

        $factory = app(EmailProviderFactory::class);
        $languageService = app(LanguageDetectionService::class);
        $totalProcessed = 0;

        foreach ($accounts as $account) {
            $this->info("Syncing emails for: {$account->email_address}");

            try {
                $provider = $factory->createProvider($account);

                if (! $provider->isAuthenticated()) {
                    $this->error("Account {$account->email_address} is not properly authenticated.");

                    continue;
                }

                $emails = $provider->fetchEmails(1000); // Fetch up to 1000 emails
                $processedCount = 0;

                $this->withProgressBar($emails, function ($emailData) use ($account, $languageService, &$processedCount) {
                    // Check if email already exists
                    $existingEmail = EmailMessage::where('message_id', $emailData['message_id'])
                        ->where('email_account_id', $account->id)
                        ->first();

                    if ($existingEmail) {
                        return; // Skip if already processed
                    }

                    // Find or create customer
                    $customer = Customer::firstOrCreate(
                        [
                            'email' => $emailData['sender_email'],
                            'company_id' => $account->company_id,
                        ],
                        [
                            'name' => $emailData['sender_name'],
                            'first_contact_at' => now(),
                            'journey_stage' => 'initial',
                        ]
                    );

                    // Detect language
                    $textToAnalyze = $emailData['subject'].' '.$emailData['body_content'];
                    $languageResult = $languageService->detectLanguage($textToAnalyze);

                    // Create email message with language detection
                    EmailMessage::create([
                        'email_account_id' => $account->id,
                        'customer_id' => $customer->id,
                        'message_id' => $emailData['message_id'],
                        'thread_id' => $emailData['thread_id'] ?? null,
                        'subject' => $emailData['subject'],
                        'body_content' => $emailData['body_content'],
                        'sender_email' => $emailData['sender_email'],
                        'sender_name' => $emailData['sender_name'],
                        'received_at' => $emailData['received_at'],
                        'detected_language' => $languageResult['primary_language'],
                        'language_confidence' => $languageResult['confidence'],
                        'status' => 'pending',
                    ]);

                    $processedCount++;
                });

                $this->newLine();
                $this->info("Processed {$processedCount} new emails for {$account->email_address}");
                $totalProcessed += $processedCount;

                // Update last sync time
                $account->update(['last_sync_at' => now()]);

            } catch (\Exception $e) {
                $this->error("Error syncing {$account->email_address}: ".$e->getMessage());
            }
        }

        $this->info("Total emails processed: {$totalProcessed}");
        $this->info('Total emails in database: '.EmailMessage::count());

        return 0;
    }
}
