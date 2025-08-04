<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailProviderFactory;
use App\Services\GmailService;
use Illuminate\Console\Command;

class SyncGmailAliases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:sync-aliases {accountId? : The email account ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Gmail send-as addresses (aliases) for email accounts';

    /**
     * Execute the console command.
     */
    public function handle(EmailProviderFactory $providerFactory): int
    {
        $accountId = $this->argument('accountId');
        
        if ($accountId) {
            $accounts = EmailAccount::where('id', $accountId)
                ->where('provider', 'gmail')
                ->where('is_active', true)
                ->get();
        } else {
            $accounts = EmailAccount::where('provider', 'gmail')
                ->where('is_active', true)
                ->get();
        }
        
        if ($accounts->isEmpty()) {
            $this->error('No active Gmail accounts found.');
            return 1;
        }
        
        $this->info("Syncing aliases for {$accounts->count()} Gmail account(s)...");
        
        foreach ($accounts as $account) {
            $this->info("Processing account: {$account->email_address}");
            
            try {
                $provider = $providerFactory->createProvider($account);
                
                if (!$provider instanceof GmailService) {
                    $this->error("Account {$account->email_address} is not a Gmail account.");
                    continue;
                }
                
                // Sync the send-as addresses
                $provider->syncSendAsAddresses();
                
                // Display the synced aliases
                $aliases = $account->aliases()->get();
                if ($aliases->count() > 0) {
                    $this->info("  Found {$aliases->count()} alias(es):");
                    foreach ($aliases as $alias) {
                        $status = $alias->is_verified ? '✓' : '✗';
                        $default = $alias->is_default ? ' (default)' : '';
                        $this->info("    [{$status}] {$alias->email_address}{$default}");
                        
                        if ($alias->name) {
                            $this->info("        Name: {$alias->name}");
                        }
                        
                        // Check if it's a custom domain that might need special handling
                        if (!str_ends_with($alias->email_address, '@gmail.com') && !$alias->is_default) {
                            $settings = $alias->settings ?? [];
                            $treatAsAlias = $settings['treat_as_alias'] ?? true;
                            
                            if ($treatAsAlias) {
                                $this->warn("        ⚠️  Custom domain detected. For proper 'From' address handling:");
                                $this->warn("        1. Go to Gmail Settings > Accounts > Send mail as");
                                $this->warn("        2. Edit '{$alias->email_address}'");
                                $this->warn("        3. Uncheck 'Treat as an alias'");
                            }
                        }
                    }
                } else {
                    $this->info("  No aliases found.");
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to sync aliases: {$e->getMessage()}");
            }
        }
        
        $this->info('Alias sync completed.');
        return 0;
    }
}