<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run AdminUserSeeder first - creates admin user needed by other seeders
        $this->call(AdminUserSeeder::class);
        
        // Run LithuanianPromptsSeeder - creates platform-wide prompts
        $this->call(LithuanianPromptsSeeder::class);
        
        // Additional seeders can be added here
        // $this->call([
        //     TestDataSeeder::class,
        // ]);
    }
}
