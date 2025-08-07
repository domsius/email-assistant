<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Console\Command;

class CheckAdminAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:check-access';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check what data the admin user has access to';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        
        if (!$admin) {
            $this->error('Admin user not found');
            return 1;
        }
        
        $this->info('=== Admin Access Check ===');
        $this->info('Admin User ID: ' . $admin->id);
        $this->info('Admin Company ID: ' . $admin->company_id);
        $this->info('Admin Role: ' . $admin->role);
        $this->line('');
        
        // Check email accounts in admin's company
        $emailAccounts = EmailAccount::where('company_id', $admin->company_id)->get();
        $this->info('Email Accounts in Company:');
        foreach ($emailAccounts as $account) {
            $user = User::find($account->user_id);
            $this->line("  - {$account->email_address}");
            $this->line("    Account ID: {$account->id}");
            $this->line("    User ID: {$account->user_id}");
            $this->line("    User Name: " . ($user ? $user->name : 'N/A'));
            $this->line("    User Email: " . ($user ? $user->email : 'N/A'));
        }
        
        $this->line('');
        
        // Check other users in the same company
        $otherUsers = User::where('company_id', $admin->company_id)
            ->where('id', '!=', $admin->id)
            ->get();
        
        $this->info('Other Users in Same Company:');
        if ($otherUsers->count() > 0) {
            foreach ($otherUsers as $user) {
                $this->line("  - {$user->name} ({$user->email})");
                $this->line("    User ID: {$user->id}");
                $this->line("    Role: {$user->role}");
            }
        } else {
            $this->line('  No other users in the company');
        }
        
        $this->line('');
        
        // Check email message counts
        $totalEmails = 0;
        foreach ($emailAccounts as $account) {
            $count = EmailMessage::where('email_account_id', $account->id)->count();
            $totalEmails += $count;
            if ($count > 0) {
                $this->line("  Account {$account->email_address}: {$count} emails");
            }
        }
        
        $this->info("Total Emails Accessible: {$totalEmails}");
        $this->line('');
        
        // Show recommendation
        $this->warn('=== Recommendation ===');
        if ($emailAccounts->count() > 0 && $emailAccounts->where('user_id', '!=', $admin->id)->count() > 0) {
            $this->error('⚠ Admin has access to email accounts belonging to other users!');
            $this->info('The system is designed for company-wide email management.');
            $this->info('If you want user-specific isolation, email accounts should be filtered by user_id.');
        } else {
            $this->info('✓ Admin only has access to their own email accounts or company shared accounts.');
        }
        
        return 0;
    }
}