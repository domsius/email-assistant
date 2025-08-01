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
        // For MySQL, we need to use raw SQL to modify ENUM column
        DB::statement("ALTER TABLE email_drafts MODIFY COLUMN action ENUM('new', 'reply', 'replyAll', 'forward', 'draft') DEFAULT 'new'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, update any 'draft' values to 'new' to avoid data loss
        DB::table('email_drafts')->where('action', 'draft')->update(['action' => 'new']);
        
        // Then modify the column back to original ENUM values
        DB::statement("ALTER TABLE email_drafts MODIFY COLUMN action ENUM('new', 'reply', 'replyAll', 'forward') DEFAULT 'new'");
    }
};