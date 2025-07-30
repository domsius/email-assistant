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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->integer('file_size');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'processing', 'processed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('chunk_count')->default(0);
            $table->json('metadata')->nullable();
            $table->string('elasticsearch_index')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
