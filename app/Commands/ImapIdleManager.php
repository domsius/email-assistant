<?php

namespace App\Commands;

use App\Models\EmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ImapIdleManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:idle-manager 
                            {--account=* : Specific account IDs to listen to}
                            {--all : Listen to all IMAP accounts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage IMAP IDLE listeners for all accounts';

    private array $processes = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting IMAP IDLE Manager...');
        
        // Get accounts to monitor
        $accounts = $this->getAccounts();
        
        if ($accounts->isEmpty()) {
            $this->warn('No IMAP accounts found to monitor.');
            return 0;
        }

        $this->info("Monitoring {$accounts->count()} IMAP account(s)");

        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }

        // Start a listener process for each account
        foreach ($accounts as $account) {
            $this->startListener($account);
        }

        // Monitor processes and restart if they fail
        while (true) {
            foreach ($this->processes as $accountId => $process) {
                if (!$process->isRunning()) {
                    $this->warn("Listener for account {$accountId} stopped. Restarting...");
                    
                    $account = EmailAccount::find($accountId);
                    if ($account && $account->is_active) {
                        $this->startListener($account);
                    } else {
                        unset($this->processes[$accountId]);
                    }
                }
            }
            
            // Check for new accounts every minute
            sleep(60);
            
            // Check for new IMAP accounts that need listeners
            $currentAccountIds = array_keys($this->processes);
            $newAccounts = $this->getAccounts()->whereNotIn('id', $currentAccountIds);
            
            foreach ($newAccounts as $account) {
                $this->info("Found new IMAP account: {$account->email_address}");
                $this->startListener($account);
            }
            
            // Remove listeners for deleted/inactive accounts
            foreach ($currentAccountIds as $accountId) {
                $account = EmailAccount::find($accountId);
                if (!$account || !$account->is_active || $account->provider !== 'imap') {
                    $this->info("Stopping listener for account {$accountId}");
                    $this->stopListener($accountId);
                }
            }
        }
    }

    private function getAccounts()
    {
        $query = EmailAccount::where('provider', 'imap')
            ->where('is_active', true);

        if ($this->option('all')) {
            return $query->get();
        }

        $accountIds = $this->option('account');
        if (!empty($accountIds)) {
            return $query->whereIn('id', $accountIds)->get();
        }

        // Default to all active IMAP accounts
        return $query->get();
    }

    private function startListener(EmailAccount $account)
    {
        $this->info("Starting IDLE listener for {$account->email_address} (ID: {$account->id})");
        
        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'imap:idle',
            $account->id,
        ];

        $process = new Process($command);
        $process->setTimeout(null); // No timeout
        $process->start(function ($type, $buffer) use ($account) {
            // Log output from child processes
            if ($type === Process::ERR) {
                Log::error("IMAP IDLE [{$account->email_address}]: {$buffer}");
            } else {
                Log::info("IMAP IDLE [{$account->email_address}]: {$buffer}");
            }
        });

        $this->processes[$account->id] = $process;
    }

    private function stopListener($accountId)
    {
        if (isset($this->processes[$accountId])) {
            $this->processes[$accountId]->stop();
            unset($this->processes[$accountId]);
        }
    }

    public function shutdown()
    {
        $this->info('Shutting down IMAP IDLE Manager...');
        
        foreach ($this->processes as $accountId => $process) {
            $this->info("Stopping listener for account {$accountId}");
            $process->stop();
        }
        
        exit(0);
    }
}