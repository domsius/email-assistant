<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailProviderFactory;
use Illuminate\Console\Command;

class SyncGmailAliases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:gmail-aliases {accountId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Gmail send-as addresses (aliases) for Gmail accounts';

    private EmailProviderFactory $providerFactory;

    public function __construct(EmailProviderFactory $providerFactory)
    {
        parent::__construct();
        $this->providerFactory = $providerFactory;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->argument('accountId');
        
        $query = EmailAccount::where('provider', 'gmail')
            ->where('is_active', true);
        
        if ($accountId) {
            $query->where('id', $accountId);
        }
        
        $accounts = $query->get();
        
        if ($accounts->isEmpty()) {
            $this->error('No active Gmail accounts found.');
            return 1;
        }
        
        $this->info("Found {$accounts->count()} Gmail account(s) to sync.");
        
        foreach ($accounts as $account) {
            $this->info("\nSyncing aliases for: {$account->email_address}");
            
            try {
                $provider = $this->providerFactory->createProvider($account);
                
                if (!$provider->isAuthenticated()) {
                    $this->error("Account not authenticated: {$account->email_address}");
                    continue;
                }
                
                if (method_exists($provider, 'syncSendAsAddresses')) {
                    $provider->syncSendAsAddresses();
                    
                    // Reload the account with aliases
                    $account->load('aliases');
                    
                    if ($account->aliases->count() > 0) {
                        $this->info("Found {$account->aliases->count()} alias(es):");
                        foreach ($account->aliases as $alias) {
                            $this->line("  - {$alias->email_address}" . ($alias->name ? " ({$alias->name})" : ''));
                        }
                    } else {
                        $this->warn("No aliases found for this account.");
                    }
                } else {
                    $this->error("Provider does not support syncing aliases.");
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to sync aliases: " . $e->getMessage());
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
        }
        
        $this->info("\nSync completed!");
        return 0;
    }
}
