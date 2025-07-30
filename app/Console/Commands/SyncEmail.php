<?php

namespace App\Console\Commands;

use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use Illuminate\Console\Command;

class SyncEmail extends Command
{
    protected $signature = 'email:sync {accountId?} {--all : Fetch all emails, not just unread} {--limit=50 : Number of emails to sync}';

    protected $description = 'Sync emails for an account';

    public function handle()
    {
        $accountId = $this->argument('accountId');
        $fetchAll = $this->option('all');
        $limit = (int) $this->option('limit');

        $options = [
            'fetch_all' => $fetchAll,
            'limit' => $limit,
            'batch_size' => min(50, $limit), // Use larger batch size
        ];

        if ($accountId) {
            $account = EmailAccount::find($accountId);
            if (! $account) {
                $this->error('Email account not found');

                return 1;
            }

            SyncEmailAccountJob::dispatch($account, $options);
            $this->info("Sync job dispatched for: {$account->email_address}");
            $this->info('Options: '.($fetchAll ? 'Fetching ALL emails' : 'Fetching only UNREAD emails').", Limit: {$limit}");
        } else {
            $accounts = EmailAccount::where('is_active', true)->get();

            foreach ($accounts as $account) {
                SyncEmailAccountJob::dispatch($account, $options);
                $this->info("Sync job dispatched for: {$account->email_address}");
            }
            $this->info('Options: '.($fetchAll ? 'Fetching ALL emails' : 'Fetching only UNREAD emails').", Limit: {$limit}");
        }

        return 0;
    }
}
