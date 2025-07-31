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
        Schema::table('email_messages', function (Blueprint $table) {
            // Change body_html from TEXT (65KB) to LONGTEXT (4GB)
            $table->longText('body_html')->nullable()->change();
            
            // Also change body_plain to LONGTEXT for consistency
            $table->longText('body_plain')->nullable()->change();
            
            // Change body_content as well if it exists
            if (Schema::hasColumn('email_messages', 'body_content')) {
                $table->longText('body_content')->nullable()->change();
            }
        });
        
        // For drafts table if it exists
        if (Schema::hasTable('drafts')) {
            Schema::table('drafts', function (Blueprint $table) {
                $table->longText('body')->nullable()->change();
            });
        }
        
        // For email_metadata if it stores content
        if (Schema::hasTable('email_metadata') && Schema::hasColumn('email_metadata', 'stored_content')) {
            Schema::table('email_metadata', function (Blueprint $table) {
                $table->longText('stored_content')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            // Revert back to TEXT - Note: This could fail if data exceeds TEXT limit
            $table->text('body_html')->nullable()->change();
            $table->text('body_plain')->nullable()->change();
            
            if (Schema::hasColumn('email_messages', 'body_content')) {
                $table->text('body_content')->nullable()->change();
            }
        });
        
        if (Schema::hasTable('drafts')) {
            Schema::table('drafts', function (Blueprint $table) {
                $table->text('body')->nullable()->change();
            });
        }
        
        if (Schema::hasTable('email_metadata') && Schema::hasColumn('email_metadata', 'stored_content')) {
            Schema::table('email_metadata', function (Blueprint $table) {
                $table->text('stored_content')->nullable()->change();
            });
        }
    }
};
