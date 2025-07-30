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
            // Add recipient fields for sent emails
            $table->json('to_recipients')->nullable()->after('sender_name');
            $table->json('cc_recipients')->nullable()->after('to_recipients');
            $table->json('bcc_recipients')->nullable()->after('cc_recipients');

            // Add threading fields for replies
            $table->string('in_reply_to')->nullable()->after('thread_id');
            $table->text('references')->nullable()->after('in_reply_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn(['to_recipients', 'cc_recipients', 'bcc_recipients', 'in_reply_to', 'references']);
        });
    }
};
