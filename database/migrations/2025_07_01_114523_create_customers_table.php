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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('preferred_language', 5)->default('lt');
            $table->enum('category', ['new', 'returning', 'vip', 'problematic'])->default('new');
            $table->json('communication_preferences')->nullable();
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->integer('total_interactions')->default(0);
            $table->integer('total_follow_ups_sent')->default(0);
            $table->decimal('satisfaction_score', 3, 2)->nullable();
            $table->string('journey_stage', 50)->default('initial');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'email']);
            $table->index(['company_id', 'category']);
            $table->index(['journey_stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
