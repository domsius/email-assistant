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
        Schema::create('email_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_id')->nullable();
            $table->string('thread_id')->nullable();
            $table->string('subject_hash', 64); // SHA256 hash of subject
            $table->string('content_hash', 64); // SHA256 hash of content for duplicate detection
            $table->string('sender_email');
            $table->string('sender_name')->nullable();
            $table->string('detected_language', 5)->default('lt');
            $table->decimal('language_confidence', 3, 2)->nullable();
            $table->decimal('topic_confidence', 3, 2)->nullable();
            $table->timestamp('received_at');
            $table->enum('status', ['pending', 'processed', 'ignored'])->default('pending');
            $table->boolean('is_reply')->default(false);
            $table->foreignId('replied_to_message_id')->nullable()->constrained('email_metadata')->nullOnDelete();

            // Aggregated metrics
            $table->integer('word_count')->default(0);
            $table->decimal('sentiment_score', 3, 2)->nullable(); // -1 to 1
            $table->decimal('urgency_score', 3, 2)->nullable(); // 0 to 1
            $table->timestamp('processing_timestamp')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['email_account_id', 'status']);
            $table->index(['customer_id', 'received_at']);
            $table->index(['thread_id']);
            $table->index(['detected_language']);
            $table->index(['message_id']);
            $table->unique(['email_account_id', 'message_id']);
            $table->index(['content_hash']); // For duplicate detection
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_metadata');
    }
};
