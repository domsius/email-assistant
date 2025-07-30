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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draft_response_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('task_type', ['initial_response', 'follow_up', 'escalation', 'customer_reply'])->default('initial_response');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->integer('estimated_duration_minutes')->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            $table->timestamp('deadline');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('auto_assigned')->default(true);
            $table->decimal('specialization_match_score', 3, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['company_id', 'priority']);
            $table->index(['deadline', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
