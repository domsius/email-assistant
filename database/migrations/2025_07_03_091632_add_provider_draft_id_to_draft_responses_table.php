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
        Schema::table('draft_responses', function (Blueprint $table) {
            $table->string('provider_draft_id')->nullable()->after('status')->comment('Draft ID from Gmail/Outlook');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draft_responses', function (Blueprint $table) {
            $table->dropColumn('provider_draft_id');
        });
    }
};
