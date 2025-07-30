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
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sent_message_id')->nullable();
            $table->enum('type', ['customer_response_check', 'polite_reminder', 'manager_follow_up']);
            $table->timestamp('scheduled_at');
            $table->timestamp('executed_at')->nullable();
            $table->enum('status', ['scheduled', 'sent', 'completed', 'cancelled'])->default('scheduled');
            $table->longText('reminder_content')->nullable();
            $table->boolean('response_received')->default(false);
            $table->integer('response_time_hours')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scheduled_at', 'status']);
            $table->index(['customer_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
