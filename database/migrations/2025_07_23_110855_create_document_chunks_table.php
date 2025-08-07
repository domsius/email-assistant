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
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('chunk_number')->default(0);
            $table->text('content');
            $table->text('embedding')->nullable(); // Store as JSON text
            $table->integer('start_position');
            $table->integer('end_position');
            $table->json('metadata')->nullable();
            $table->string('elasticsearch_id')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'chunk_number']);
            $table->index('elasticsearch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
