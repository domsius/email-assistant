<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear encrypted OAuth tokens that cannot be decrypted with current APP_KEY
        // This forces users to re-authenticate, which is safer than data corruption
        DB::table('email_accounts')->update([
            'access_token' => null,
            'refresh_token' => null,
            'provider_settings' => null,
            'is_active' => false,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - clearing tokens is irreversible by design
    }
};
