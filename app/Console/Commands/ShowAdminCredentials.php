<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ShowAdminCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:credentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show test admin user credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        
        if (!$admin) {
            $this->error('Admin user not found. Please run: php artisan db:seed --class=AdminUserSeeder');
            return 1;
        }
        
        $this->info('================================');
        $this->info('   ADMIN USER CREDENTIALS');
        $this->info('================================');
        $this->line('');
        $this->info('Email: admin@example.com');
        $this->info('Password: password');
        $this->line('');
        $this->info('Admin Panel URL: /admin/global-prompts');
        $this->line('');
        
        if ($admin->role === 'admin') {
            $this->info('✓ User has admin role');
        } else {
            $this->warn('⚠ User does not have admin role. Run the seeder again.');
        }
        
        $promptCount = $admin->company->globalPrompts()->count();
        if ($promptCount > 0) {
            $this->info("✓ {$promptCount} global AI prompts configured");
            
            $activePrompts = $admin->company->globalPrompts()->where('is_active', true)->get();
            if ($activePrompts->count() > 0) {
                $this->line('');
                $this->info('Active Prompts:');
                foreach ($activePrompts as $prompt) {
                    $this->line("  - {$prompt->name} ({$prompt->prompt_type})");
                }
            }
        } else {
            $this->line('  No global prompts configured yet');
        }
        
        $this->line('');
        $this->warn('⚠ IMPORTANT: Change the password after first login!');
        $this->info('================================');
        
        return 0;
    }
}