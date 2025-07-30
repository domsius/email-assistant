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
        Schema::create('emotion_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained()->cascadeOnDelete();
            $table->integer('urgency')->default(1); // 1-5 scale
            $table->integer('negativity')->default(1); // 1-5 scale
            $table->integer('satisfaction')->default(3); // 1-5 scale
            $table->decimal('confidence_score', 3, 2)->nullable();
            $table->integer('recommended_response_time')->default(24); // hours
            $table->json('detected_emotions')->nullable();
            $table->text('analysis_summary')->nullable();
            $table->timestamps();

            $table->index(['urgency']);
            $table->index(['negativity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emotion_analyses');
    }
};
