<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            // Add imap_username and smtp_username fields if they don't exist
            if (!Schema::hasColumn('email_accounts', 'imap_username')) {
                $table->string('imap_username')->nullable()->after('imap_encryption');
            }
            if (!Schema::hasColumn('email_accounts', 'smtp_username')) {
                $table->string('smtp_username')->nullable()->after('smtp_encryption');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('email_accounts', 'imap_username')) {
                $table->dropColumn('imap_username');
            }
            if (Schema::hasColumn('email_accounts', 'smtp_username')) {
                $table->dropColumn('smtp_username');
            }
        });
    }
};