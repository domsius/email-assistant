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
        Schema::table('email_messages', function (Blueprint $table) {
            // Make customer_id nullable to allow saving sent emails without customer association
            $table->foreignId('customer_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            // Note: This will fail if there are null customer_id values in the table
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }
};