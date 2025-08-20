<?php

namespace App\Commands;

use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

class ImapIdleListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:idle {account_id : The email account ID to listen to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for new emails using IMAP IDLE';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->argument('account_id');
        $emailAccount = EmailAccount::find($accountId);

        if (!$emailAccount || $emailAccount->provider !== 'imap') {
            $this->error('Invalid IMAP account');
            return 1;
        }

        $this->info("Starting IMAP IDLE listener for {$emailAccount->email_address}");
        Log::info('IMAP IDLE listener started', ['account_id' => $accountId]);

        try {
            $manager = new ClientManager();
            
            // Use imap_username if set, otherwise fall back to email_address
            $username = $emailAccount->imap_username ?? $emailAccount->email_address;
            
            $config = [
                'host' => $emailAccount->imap_host,
                'port' => $emailAccount->imap_port ?? 993,
                'encryption' => $emailAccount->imap_encryption ?? 'ssl',
                'validate_cert' => $emailAccount->imap_validate_cert ?? true,
                'username' => $username,
                'password' => $emailAccount->imap_password,
                'protocol' => 'imap',
                'authentication' => 'login',
            ];

            $client = $manager->make($config);
            $client->connect();

            $folder = $client->getFolder('INBOX');
            
            // Start IDLE mode
            $this->info("Entering IDLE mode. Listening for new emails...");
            
            while (true) {
                try {
                    // Enter IDLE state - this will block until a new message arrives or timeout (29 minutes)
                    $folder->idle(function($message) use ($emailAccount) {
                        $this->info("New email detected!");
                        Log::info('IMAP IDLE: New email detected', [
                            'account_id' => $emailAccount->id,
                            'email' => $emailAccount->email_address,
                        ]);
                        
                        // Dispatch a quick sync job to fetch the new email
                        SyncEmailAccountJob::dispatch($emailAccount, [
                            'quick_sync' => true,
                            'limit' => 5,
                            'fetch_all' => false, // Only fetch new/unseen emails
                        ]);
                        
                        // Return false to continue listening
                        return false;
                    }, 1740); // 29 minutes timeout (IMAP servers typically timeout at 30 minutes)
                    
                    // Send a NOOP command to keep connection alive
                    $client->checkConnection();
                    
                } catch (\Exception $e) {
                    $this->warn("IDLE connection lost, reconnecting: " . $e->getMessage());
                    Log::warning('IMAP IDLE connection lost', [
                        'account_id' => $emailAccount->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // Try to reconnect
                    try {
                        $client->disconnect();
                    } catch (\Exception $disconnectError) {
                        // Ignore disconnect errors
                    }
                    
                    sleep(5); // Wait before reconnecting
                    $client->connect();
                    $folder = $client->getFolder('INBOX');
                }
            }

        } catch (\Exception $e) {
            $this->error("IMAP IDLE listener failed: " . $e->getMessage());
            Log::error('IMAP IDLE listener failed', [
                'account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }
    }
}