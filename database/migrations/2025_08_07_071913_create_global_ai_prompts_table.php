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
        Schema::create('global_ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('prompt_content');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('prompt_type')->default('general'); // general, rag_enhanced, support, sales, etc.
            $table->json('settings')->nullable(); // Additional settings like temperature, max_tokens, etc.
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['company_id', 'is_active']);
            $table->index('prompt_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_ai_prompts');
    }
};