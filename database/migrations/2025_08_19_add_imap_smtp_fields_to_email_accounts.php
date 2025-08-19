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
            // Check if columns don't already exist before adding them
            if (!Schema::hasColumn('email_accounts', 'imap_host')) {
                $table->string('imap_host')->nullable()->after('provider_settings');
            }
            if (!Schema::hasColumn('email_accounts', 'imap_port')) {
                $table->integer('imap_port')->nullable()->default(993)->after('imap_host');
            }
            if (!Schema::hasColumn('email_accounts', 'imap_encryption')) {
                $table->string('imap_encryption')->nullable()->default('ssl')->after('imap_port');
            }
            if (!Schema::hasColumn('email_accounts', 'imap_password')) {
                $table->text('imap_password')->nullable()->after('imap_encryption');
            }
            if (!Schema::hasColumn('email_accounts', 'imap_validate_cert')) {
                $table->boolean('imap_validate_cert')->default(true)->after('imap_password');
            }
            
            // SMTP settings
            if (!Schema::hasColumn('email_accounts', 'smtp_host')) {
                $table->string('smtp_host')->nullable()->after('imap_validate_cert');
            }
            if (!Schema::hasColumn('email_accounts', 'smtp_port')) {
                $table->integer('smtp_port')->nullable()->default(587)->after('smtp_host');
            }
            if (!Schema::hasColumn('email_accounts', 'smtp_encryption')) {
                $table->string('smtp_encryption')->nullable()->default('tls')->after('smtp_port');
            }
            if (!Schema::hasColumn('email_accounts', 'smtp_password')) {
                $table->text('smtp_password')->nullable()->after('smtp_encryption');
            }
            
            // Add sender name for generic accounts
            if (!Schema::hasColumn('email_accounts', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('email_address');
            }
            
            // sync_error field already exists, don't add it again
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host',
                'imap_port',
                'imap_encryption',
                'imap_password',
                'imap_validate_cert',
                'smtp_host',
                'smtp_port',
                'smtp_encryption',
                'smtp_password',
                'sender_name',
                'sync_error',
            ]);
        });
    }
};