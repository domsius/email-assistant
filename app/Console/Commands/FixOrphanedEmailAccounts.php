<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\User;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FixOrphanedEmailAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:fix-orphaned 
                            {--dry-run : Show what would be done without making changes}
                            {--delete : Delete orphaned accounts instead of assigning them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix email accounts with NULL user_id by assigning them to appropriate users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $shouldDelete = $this->option('delete');
        
        $this->info('=== Fixing Orphaned Email Accounts ===');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        // Find orphaned email accounts
        $orphanedAccounts = EmailAccount::whereNull('user_id')->get();
        
        if ($orphanedAccounts->isEmpty()) {
            $this->info('✓ No orphaned email accounts found!');
            return 0;
        }
        
        $this->info("Found {$orphanedAccounts->count()} orphaned email account(s)");
        $this->line('');
        
        $fixed = 0;
        $deleted = 0;
        $failed = 0;
        
        foreach ($orphanedAccounts as $account) {
            $this->line("Processing: {$account->email_address} (ID: {$account->id})");
            
            if ($shouldDelete) {
                if (!$isDryRun) {
                    $account->delete();
                }
                $this->info("  → Would delete account");
                $deleted++;
            } else {
                // Find an appropriate user to assign the account to
                $user = $this->findUserForAccount($account);
                
                if ($user) {
                    if (!$isDryRun) {
                        $account->user_id = $user->id;
                        $account->save();
                    }
                    $this->info("  → Assigned to user: {$user->email} (ID: {$user->id})");
                    $fixed++;
                } else {
                    $this->error("  → No suitable user found");
                    
                    // Create a default user for the company if none exists
                    if (!$isDryRun) {
                        $user = $this->createDefaultUserForCompany($account->company_id);
                        if ($user) {
                            $account->user_id = $user->id;
                            $account->save();
                            $this->info("  → Created and assigned to new user: {$user->email}");
                            $fixed++;
                        } else {
                            $failed++;
                        }
                    } else {
                        $this->warn("  → Would create a default user for company ID: {$account->company_id}");
                        $fixed++;
                    }
                }
            }
        }
        
        $this->line('');
        $this->info('=== Summary ===');
        $this->info("Fixed: {$fixed} account(s)");
        $this->info("Deleted: {$deleted} account(s)");
        $this->error("Failed: {$failed} account(s)");
        
        if ($isDryRun) {
            $this->warn('Run without --dry-run to apply changes');
        }
        
        return $failed > 0 ? 1 : 0;
    }
    
    /**
     * Find an appropriate user for the email account
     */
    private function findUserForAccount(EmailAccount $account): ?User
    {
        // Strategy 1: Find the first admin user in the company
        $admin = User::where('company_id', $account->company_id)
            ->where('role', 'admin')
            ->first();
            
        if ($admin) {
            return $admin;
        }
        
        // Strategy 2: Find the first active user in the company
        $activeUser = User::where('company_id', $account->company_id)
            ->where('is_active', true)
            ->first();
            
        if ($activeUser) {
            return $activeUser;
        }
        
        // Strategy 3: Find any user in the company
        return User::where('company_id', $account->company_id)->first();
    }
    
    /**
     * Create a default user for a company
     */
    private function createDefaultUserForCompany(int $companyId): ?User
    {
        $company = Company::find($companyId);
        
        if (!$company) {
            $this->error("Company ID {$companyId} not found!");
            return null;
        }
        
        // Create a default admin user for the company
        return User::create([
            'name' => "{$company->name} Admin",
            'email' => "admin.{$companyId}@" . config('app.domain', 'example.com'),
            'password' => bcrypt(Str::random(32)),
            'company_id' => $companyId,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}