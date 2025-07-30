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
        Schema::create('email_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->string('to')->nullable();
            $table->string('cc')->nullable();
            $table->string('bcc')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->string('references')->nullable();
            $table->enum('action', ['new', 'reply', 'replyAll', 'forward'])->default('new');
            $table->unsignedBigInteger('original_email_id')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('email_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_drafts');
    }
};
