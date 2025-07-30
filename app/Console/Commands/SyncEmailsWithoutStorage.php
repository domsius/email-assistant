<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailProcessingService;
use App\Services\EmailProviderFactory;
use Illuminate\Console\Command;

class SyncEmailsWithoutStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:sync-metadata {--account= : Specific account ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync email metadata without storing email content (GDPR compliant)';

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
        $processingService = app(EmailProcessingService::class);
        $totalProcessed = 0;

        foreach ($accounts as $account) {
            $this->info("Syncing email metadata for: {$account->email_address}");

            try {
                $provider = $factory->createProvider($account);

                if (! $provider->isAuthenticated()) {
                    $this->error("Account {$account->email_address} is not properly authenticated.");

                    continue;
                }

                $emails = $provider->fetchEmails(1000); // Fetch up to 1000 emails
                $processedCount = 0;

                $this->withProgressBar($emails, function ($emailData) use ($account, $processingService, &$processedCount) {
                    // Process email without storing content
                    $metadata = $processingService->processEmailWithoutStorage($emailData, $account);

                    if ($metadata) {
                        $processedCount++;
                    }
                });

                $this->newLine();
                $this->info("Processed {$processedCount} new email metadata records for {$account->email_address}");
                $totalProcessed += $processedCount;

                // Update last sync time
                $account->update(['last_sync_at' => now()]);

            } catch (\Exception $e) {
                $this->error("Error syncing {$account->email_address}: ".$e->getMessage());
            }
        }

        $this->info("Total email metadata processed: {$totalProcessed}");

        return 0;
    }
}
