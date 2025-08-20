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
            $table->string('outlook_subscription_id')->nullable()->after('gmail_history_id');
            $table->timestamp('outlook_subscription_expires_at')->nullable()->after('outlook_subscription_id');
            $table->string('outlook_webhook_token')->nullable()->after('outlook_subscription_expires_at');
            
            // Add index for faster lookups
            $table->index('outlook_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropIndex(['outlook_subscription_id']);
            $table->dropColumn([
                'outlook_subscription_id',
                'outlook_subscription_expires_at', 
                'outlook_webhook_token'
            ]);
        });
    }
};
