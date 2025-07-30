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
        Schema::table('users', function (Blueprint $table) {
            // SQLite doesn't support enum modification, so we need to recreate if needed
            if (! \Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('employee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // No rollback needed for column that might already exist
        });
    }
};
