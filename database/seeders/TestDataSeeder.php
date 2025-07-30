<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test company
        $company = Company::create([
            'name' => 'Test Company',
            'email_limit' => 5000,
            'subscription_plan' => 'pro',
            'supported_languages' => ['en', 'lt', 'de', 'fr'],
            'escalation_settings' => [],
            'business_hours' => [],
            'follow_up_settings' => [],
            'is_active' => true,
        ]);

        // Create a test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role' => 'admin',
            'is_active' => true,
            'workload_capacity' => 100,
            'current_workload' => 0,
        ]);
    }
}
