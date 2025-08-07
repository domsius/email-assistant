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
            // Add user_id column to track which user owns this email account
            $table->foreignId('user_id')->nullable()->after('company_id')->constrained('users')->cascadeOnDelete();
            
            // Add index for faster queries
            $table->index(['company_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};