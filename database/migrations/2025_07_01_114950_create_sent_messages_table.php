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
        Schema::create('sent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_email_id')->constrained('email_messages')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 500);
            $table->longText('content');
            $table->string('language', 5)->default('lt');
            $table->timestamp('sent_at');
            $table->decimal('response_time_hours', 5, 2)->nullable();
            $table->boolean('was_ai_generated')->default(true);
            $table->decimal('ai_confidence_score', 3, 2)->nullable();
            $table->string('template_used', 100)->nullable();
            $table->enum('delivery_status', ['sent', 'delivered', 'opened', 'replied', 'bounced'])->default('sent');
            $table->timestamps();

            $table->index(['customer_id', 'sent_at']);
            $table->index(['sent_by', 'sent_at']);
            $table->index(['delivery_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sent_messages');
    }
};
