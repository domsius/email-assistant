<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, assign orphaned email accounts to their company's first user
        $orphanedAccounts = DB::table('email_accounts')
            ->whereNull('user_id')
            ->get();

        foreach ($orphanedAccounts as $account) {
            // Find the first user in the same company
            $firstUser = DB::table('users')
                ->where('company_id', $account->company_id)
                ->orderBy('id')
                ->first();

            if ($firstUser) {
                DB::table('email_accounts')
                    ->where('id', $account->id)
                    ->update(['user_id' => $firstUser->id]);
                    
                echo "Assigned email account {$account->email_address} to user {$firstUser->email}\n";
            } else {
                // If no user in company, delete the orphaned account
                // (or you could assign to a system user)
                DB::table('email_accounts')
                    ->where('id', $account->id)
                    ->delete();
                    
                echo "Deleted orphaned email account {$account->email_address} (no users in company)\n";
            }
        }

        // Note: We cannot make user_id NOT NULL with a simple change() in MySQL
        // So we'll just ensure it's properly indexed
        // The foreign key constraint already ensures referential integrity
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not safely reversible
        // as we've assigned user_ids to previously null values
    }
};