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
        Schema::table('email_messages', function (Blueprint $table) {
            // Add unique constraint on message_id and email_account_id combination
            // This prevents duplicate emails for the same account at the database level
            $table->unique(['message_id', 'email_account_id'], 'unique_message_per_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('unique_message_per_account');
        });
    }
};
