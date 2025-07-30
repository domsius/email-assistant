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
            // Add missing fields that InboxController expects
            $table->string('from_email')->nullable()->after('sender_email');
            $table->text('body_plain')->nullable()->after('body_content');
            $table->text('snippet')->nullable()->after('preview');
            $table->string('processing_status')->default('pending')->after('status');
            $table->json('labels')->nullable()->after('folder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn(['from_email', 'body_plain', 'snippet', 'processing_status', 'labels']);
        });
    }
};
