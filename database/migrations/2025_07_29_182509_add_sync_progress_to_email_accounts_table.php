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
            $table->enum('sync_status', ['idle', 'syncing', 'completed', 'failed'])->default('idle')->after('is_active');
            $table->integer('sync_progress')->default(0)->after('sync_status');
            $table->integer('sync_total')->default(0)->after('sync_progress');
            $table->text('sync_error')->nullable()->after('sync_total');
            $table->timestamp('sync_started_at')->nullable()->after('sync_error');
            $table->timestamp('sync_completed_at')->nullable()->after('sync_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'sync_status',
                'sync_progress',
                'sync_total',
                'sync_error',
                'sync_started_at',
                'sync_completed_at'
            ]);
        });
    }
};
