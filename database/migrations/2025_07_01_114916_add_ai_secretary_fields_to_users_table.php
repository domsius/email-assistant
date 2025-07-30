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
            $table->foreignId('company_id')->after('id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'manager', 'supervisor'])->after('email')->default('manager');
            $table->string('department', 100)->after('role')->nullable();
            $table->json('product_specializations')->after('department')->nullable();
            $table->json('language_skills')->after('product_specializations')->nullable();
            $table->boolean('is_active')->after('language_skills')->default(true);
            $table->integer('workload_capacity')->after('is_active')->default(10);
            $table->integer('current_workload')->after('workload_capacity')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'company_id',
                'role',
                'department',
                'product_specializations',
                'language_skills',
                'is_active',
                'workload_capacity',
                'current_workload',
            ]);
        });
    }
};
