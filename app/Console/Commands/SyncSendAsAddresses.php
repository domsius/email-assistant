<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailProviderFactory;
use Illuminate\Console\Command;

class SyncSendAsAddresses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:sendas-addresses {accountId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync send-as addresses (aliases) and signatures from email providers';

    /**
     * Execute the console command.
     */
    public function handle(EmailProviderFactory $providerFactory)
    {
        $accountId = $this->argument('accountId');
        
        if ($accountId) {
            $accounts = EmailAccount::where('id', $accountId)->get();
        } else {
            $accounts = EmailAccount::where('is_active', true)->get();
        }
        
        if ($accounts->isEmpty()) {
            $this->error('No active email accounts found');
            return 1;
        }
        
        foreach ($accounts as $account) {
            $this->info("Syncing send-as addresses for: {$account->email_address}");
            
            try {
                $provider = $providerFactory->createProvider($account);
                
                if (method_exists($provider, 'syncSendAsAddresses')) {
                    $provider->syncSendAsAddresses();
                    $this->info("✓ Successfully synced send-as addresses");
                    
                    // Show aliases
                    $aliases = $account->aliases()->get();
                    foreach ($aliases as $alias) {
                        $hasSignature = isset($alias->settings['signature']) && !empty($alias->settings['signature']);
                        $signatureIcon = $hasSignature ? '✓' : '✗';
                        $this->info("  - {$alias->email_address} [Signature: {$signatureIcon}]");
                    }
                } else {
                    $this->warn("Provider {$account->provider} does not support send-as addresses");
                }
            } catch (\Exception $e) {
                $this->error("Failed to sync: {$e->getMessage()}");
            }
        }
        
        return 0;
    }
}