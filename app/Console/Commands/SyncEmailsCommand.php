<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailSyncService;
use Illuminate\Console\Command;

class SyncEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:emails 
                            {--account= : Specific email account ID to sync}
                            {--company= : Sync all accounts for a specific company ID}
                            {--limit=50 : Number of emails to sync per account}
                            {--batch-size=10 : Number of emails to process per batch}
                            {--all : Sync all active email accounts}
                            {--all-emails : Fetch all emails (not just unread ones)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync emails from connected email accounts with optimized batch processing';

    private EmailSyncService $syncService;

    /**
     * Create a new command instance.
     */
    public function __construct(EmailSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch-size');
        $fetchAllEmails = $this->option('all-emails');

        $options = [
            'limit' => $limit,
            'batch_size' => $batchSize,
            'fetch_all' => $fetchAllEmails,
        ];

        $this->info('Starting email sync with batch processing...');
        $this->info("Limit: {$limit} emails per account");
        $this->info("Batch size: {$batchSize} emails per batch");
        $this->info('Fetch all emails: '.($fetchAllEmails ? 'Yes' : 'No (unread only)'));

        if ($accountId = $this->option('account')) {
            $this->syncSingleAccount($accountId, $options);
        } elseif ($companyId = $this->option('company')) {
            $this->syncCompanyAccounts($companyId, $options);
        } elseif ($this->option('all')) {
            $this->syncAllAccounts($options);
        } else {
            $this->error('Please specify --account, --company, or --all option');

            return 1;
        }

        return 0;
    }

    /**
     * Sync a single email account
     */
    private function syncSingleAccount(int $accountId, array $options): void
    {
        $account = EmailAccount::find($accountId);

        if (! $account) {
            $this->error("Email account with ID {$accountId} not found");

            return;
        }

        if (! $account->is_active) {
            $this->warn("Email account {$account->email_address} is not active");

            return;
        }

        $this->info("Syncing emails for: {$account->email_address}");

        $progressBar = $this->output->createProgressBar($options['limit']);
        $progressBar->start();

        $totalProcessed = 0;
        $hasMore = true;
        $pageToken = null;

        while ($hasMore && $totalProcessed < $options['limit']) {
            $syncOptions = array_merge($options, ['page_token' => $pageToken]);
            $result = $this->syncService->syncEmails($account, $syncOptions);

            if ($result['success']) {
                $totalProcessed += $result['processed'];
                $progressBar->advance($result['processed']);

                $hasMore = $result['has_more'];
                $pageToken = $result['next_page_token'] ?? null;

                if ($result['errors']) {
                    $this->newLine();
                    $errorCount = count($result['errors']);
                    $this->warn("Encountered {$errorCount} errors during sync");
                }
            } else {
                $progressBar->finish();
                $this->newLine();
                $this->error("Sync failed: {$result['error']}");

                return;
            }
        }

        $progressBar->finish();
        $this->newLine();

        $this->info('Sync completed!');
        $this->info("Processed: {$totalProcessed} emails");

        // Show sync status
        $status = $this->syncService->getSyncStatus($account);
        $this->table(
            ['Metric', 'Value'],
            [
                ['Last Sync', $status['last_sync_at']],
                ['Time Since Last Sync', $status['time_since_last_sync']],
                ['Pending Emails', $status['pending_emails']],
                ['Processed Today', $status['processed_today']],
            ]
        );
    }

    /**
     * Sync all accounts for a company
     */
    private function syncCompanyAccounts(int $companyId, array $options): void
    {
        $accounts = EmailAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn("No active email accounts found for company ID {$companyId}");

            return;
        }

        $this->info("Found {$accounts->count()} active email accounts");

        foreach ($accounts as $account) {
            $this->newLine();
            $this->syncSingleAccount($account->id, $options);
        }
    }

    /**
     * Sync all active email accounts
     */
    private function syncAllAccounts(array $options): void
    {
        $accounts = EmailAccount::where('is_active', true)->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active email accounts found');

            return;
        }

        $this->info("Found {$accounts->count()} active email accounts across all companies");

        $confirm = $this->confirm("Do you want to sync all {$accounts->count()} accounts?");

        if (! $confirm) {
            $this->info('Sync cancelled');

            return;
        }

        foreach ($accounts as $account) {
            $this->newLine();
            $this->syncSingleAccount($account->id, $options);
        }

        $this->newLine();
        $this->info('All accounts synced successfully!');
    }
}
