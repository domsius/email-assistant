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
            // Add inbox-specific fields
            $table->boolean('is_read')->default(false)->after('status');
            $table->boolean('is_starred')->default(false)->after('is_read');
            $table->boolean('has_attachments')->default(false)->after('is_starred');
            $table->text('body_html')->nullable()->after('body_content');
            $table->text('body_preview')->nullable()->after('body_html');
            $table->text('preview')->nullable()->after('body_preview');

            // AI analysis fields
            $table->string('detected_topic')->nullable()->after('detected_language');
            $table->decimal('sentiment_score', 3, 2)->nullable()->after('detected_topic');
            $table->enum('urgency_level', ['low', 'medium', 'high'])->default('low')->after('sentiment_score');
            $table->text('ai_analysis')->nullable()->after('urgency_level');

            // Update status enum to include 'processing' and 'failed'
            $table->enum('status', ['pending', 'processing', 'processed', 'ignored', 'failed'])->default('pending')->change();

            // Add indexes for performance
            $table->index(['is_read', 'received_at']);
            $table->index(['is_starred']);
            $table->index(['detected_topic']);
            $table->index(['urgency_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropIndex(['is_read', 'received_at']);
            $table->dropIndex(['is_starred']);
            $table->dropIndex(['detected_topic']);
            $table->dropIndex(['urgency_level']);

            $table->dropColumn([
                'is_read',
                'is_starred',
                'has_attachments',
                'body_html',
                'body_preview',
                'preview',
                'detected_topic',
                'sentiment_score',
                'urgency_level',
                'ai_analysis',
            ]);

            // Revert status enum
            $table->enum('status', ['pending', 'processed', 'ignored'])->default('pending')->change();
        });
    }
};
