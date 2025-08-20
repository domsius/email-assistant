<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to use raw SQL to modify ENUM
        DB::statement("ALTER TABLE email_accounts MODIFY COLUMN provider ENUM('outlook', 'gmail', 'imap') NOT NULL DEFAULT 'gmail'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'imap' from the enum (this will fail if there are existing 'imap' records)
        DB::statement("ALTER TABLE email_accounts MODIFY COLUMN provider ENUM('outlook', 'gmail') NOT NULL DEFAULT 'gmail'");
    }
};