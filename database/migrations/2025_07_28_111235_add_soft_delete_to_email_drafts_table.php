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
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();

            // Add index for better query performance
            $table->index(['user_id', 'is_deleted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_drafts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_deleted']);
            $table->dropColumn(['is_deleted', 'deleted_at']);
        });
    }
};
