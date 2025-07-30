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
        Schema::create('communication_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('follow_up_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('event_type', ['customer_message_received', 'manager_response_sent', 'customer_reply_received', 'follow_up_sent', 'escalation_triggered']);
            $table->timestamp('timestamp');
            $table->decimal('duration_from_previous_hours', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'timestamp']);
            $table->index(['event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_timelines');
    }
};
