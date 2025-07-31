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
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->string('content_type')->nullable();
            $table->integer('size')->default(0);
            $table->string('content_id')->nullable(); // For inline images
            $table->text('content_disposition')->nullable();
            $table->text('storage_path')->nullable();
            $table->text('download_url')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->timestamps();
            
            $table->index('email_message_id');
            $table->index('content_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
