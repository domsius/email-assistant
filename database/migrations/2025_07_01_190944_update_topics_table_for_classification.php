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
        Schema::table('topics', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
            $table->string('color', 7)->nullable()->after('description'); // Hex color code
            $table->string('priority', 20)->default('medium')->after('color'); // critical, high, medium, low
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn(['slug', 'color', 'priority']);
        });
    }
};
