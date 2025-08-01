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
        Schema::create('email_account_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->string('email_address');
            $table->string('name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->string('reply_to_address')->nullable();
            $table->json('settings')->nullable(); // For provider-specific settings
            $table->timestamps();
            
            $table->unique(['email_account_id', 'email_address']);
            $table->index('email_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_account_aliases');
    }
};
