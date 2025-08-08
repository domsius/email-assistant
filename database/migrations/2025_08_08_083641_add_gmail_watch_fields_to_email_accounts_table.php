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
            // Gmail Push Notification fields
            $table->string('gmail_watch_token')->nullable()->after('last_sync_at');
            $table->string('gmail_watch_expiration')->nullable()->after('gmail_watch_token');
            $table->string('gmail_history_id')->nullable()->after('gmail_watch_expiration');
            
            // Index for faster lookups
            $table->index('gmail_watch_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropIndex(['gmail_watch_token']);
            $table->dropColumn(['gmail_watch_token', 'gmail_watch_expiration', 'gmail_history_id']);
        });
    }
};