<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\OutlookWebhookService;
use Illuminate\Console\Command;

class ManageOutlookSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outlook:subscriptions {action : create|renew|cleanup|list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Outlook webhook subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(OutlookWebhookService $webhookService)
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'create':
                $this->createSubscriptions($webhookService);
                break;
            case 'renew':
                $this->renewSubscriptions($webhookService);
                break;
            case 'cleanup':
                $this->cleanupExpiredSubscriptions($webhookService);
                break;
            case 'list':
                $this->listSubscriptions();
                break;
            default:
                $this->error("Invalid action. Use: create, renew, cleanup, or list");
        }
    }

    /**
     * Create subscriptions for accounts without them
     */
    private function createSubscriptions(OutlookWebhookService $webhookService)
    {
        $accounts = EmailAccount::where('provider', 'outlook')
            ->where('is_active', true)
            ->whereNull('outlook_subscription_id')
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No Outlook accounts need new subscriptions.');
            return;
        }

        $this->info("Creating subscriptions for {$accounts->count()} Outlook accounts...");

        foreach ($accounts as $account) {
            $this->line("Processing: {$account->email_address}");
            
            $subscriptionId = $webhookService->createSubscription($account);
            if ($subscriptionId) {
                $this->info("✓ Created subscription for {$account->email_address} (ID: {$subscriptionId})");
            } else {
                $this->error("✗ Failed to create subscription for {$account->email_address}");
            }
        }
    }

    /**
     * Renew subscriptions that are expiring soon
     */
    private function renewSubscriptions(OutlookWebhookService $webhookService)
    {
        // Renew subscriptions expiring in the next 24 hours
        $accounts = EmailAccount::where('provider', 'outlook')
            ->where('is_active', true)
            ->whereNotNull('outlook_subscription_id')
            ->where('outlook_subscription_expires_at', '<', now()->addDay())
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No Outlook subscriptions need renewal.');
            return;
        }

        $this->info("Renewing {$accounts->count()} expiring subscriptions...");

        foreach ($accounts as $account) {
            $this->line("Processing: {$account->email_address}");
            $expiresIn = $account->outlook_subscription_expires_at 
                ? $account->outlook_subscription_expires_at->diffForHumans() 
                : 'unknown';
            $this->line("  Current expiry: {$expiresIn}");
            
            if ($webhookService->renewSubscription($account)) {
                $account->refresh();
                $newExpiry = $account->outlook_subscription_expires_at 
                    ? $account->outlook_subscription_expires_at->diffForHumans() 
                    : 'unknown';
                $this->info("✓ Renewed subscription for {$account->email_address} (expires: {$newExpiry})");
            } else {
                $this->error("✗ Failed to renew subscription for {$account->email_address}");
            }
        }
    }

    /**
     * Cleanup expired subscriptions
     */
    private function cleanupExpiredSubscriptions(OutlookWebhookService $webhookService)
    {
        $accounts = EmailAccount::where('provider', 'outlook')
            ->whereNotNull('outlook_subscription_id')
            ->where('outlook_subscription_expires_at', '<', now())
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No expired Outlook subscriptions to clean up.');
            return;
        }

        $this->info("Cleaning up {$accounts->count()} expired subscriptions...");

        foreach ($accounts as $account) {
            $this->line("Removing expired subscription for: {$account->email_address}");
            
            if ($webhookService->deleteSubscription($account)) {
                $this->info("✓ Cleaned up subscription for {$account->email_address}");
            } else {
                $this->error("✗ Failed to cleanup subscription for {$account->email_address}");
            }
        }
    }

    /**
     * List all subscriptions
     */
    private function listSubscriptions()
    {
        $accounts = EmailAccount::where('provider', 'outlook')
            ->where('is_active', true)
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No active Outlook accounts found.');
            return;
        }

        $headers = ['Email', 'Subscription ID', 'Expires At', 'Status'];
        $rows = [];

        foreach ($accounts as $account) {
            $status = 'No subscription';
            $expiresAt = '-';
            
            if ($account->outlook_subscription_id) {
                if ($account->outlook_subscription_expires_at) {
                    if ($account->outlook_subscription_expires_at->isPast()) {
                        $status = '⚠️ Expired';
                    } elseif ($account->outlook_subscription_expires_at->isBefore(now()->addDay())) {
                        $status = '⚠️ Expiring soon';
                    } else {
                        $status = '✓ Active';
                    }
                    $expiresAt = $account->outlook_subscription_expires_at->format('Y-m-d H:i:s');
                }
            }
            
            $rows[] = [
                $account->email_address,
                $account->outlook_subscription_id ? substr($account->outlook_subscription_id, 0, 20) . '...' : '-',
                $expiresAt,
                $status
            ];
        }

        $this->table($headers, $rows);
        
        // Summary
        $total = $accounts->count();
        $active = $accounts->filter(fn($a) => 
            $a->outlook_subscription_id && 
            $a->outlook_subscription_expires_at && 
            $a->outlook_subscription_expires_at->isFuture()
        )->count();
        
        $this->info("\nSummary: {$active}/{$total} accounts have active subscriptions");
    }
}