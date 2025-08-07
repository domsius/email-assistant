<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $adminEmail = 'admin@example.com';
        
        if (User::where('email', $adminEmail)->exists()) {
            $this->command->info('Admin user already exists, updating to ensure admin role...');
            $admin = User::where('email', $adminEmail)->first();
            $admin->update(['role' => 'admin']);
            $this->command->info('Admin user updated successfully.');
            
            // Check if prompts already exist for this company
            $company = $admin->company;
            if (!$company->globalPrompts()->exists()) {
                $this->createSamplePrompts($admin, $company);
            } else {
                $this->command->info('Global AI prompts already exist for this company.');
            }
            return;
        }

        // Create or get the first company
        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'name' => 'Admin Company',
                'industry' => 'Technology',
                'size' => '1-10',
                'website' => 'https://example.com',
            ]);
            $this->command->info('Created new company for admin user.');
        }

        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => $adminEmail,
            'password' => Hash::make('admin123456'), // Change this in production!
            'company_id' => $company->id,
            'role' => 'admin',
            'department' => 'Administration',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: ' . $adminEmail);
        $this->command->info('Password: admin123456');
        $this->command->warn('IMPORTANT: Please change the admin password after first login!');
        
        $this->createSamplePrompts($admin, $company);
    }
    
    /**
     * Create sample global AI prompts
     */
    private function createSamplePrompts(User $admin, Company $company): void
    {
        $admin->createdGlobalPrompts()->create([
            'company_id' => $company->id,
            'name' => 'Default Professional Tone',
            'prompt_content' => "You are a professional assistant representing {$company->name}. Always maintain a courteous and helpful tone. Focus on providing clear, accurate information while being concise. Prioritize customer satisfaction and problem resolution.",
            'description' => 'Default prompt for professional business communication',
            'prompt_type' => 'general',
            'is_active' => true,
            'settings' => [
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ],
        ]);
        
        $admin->createdGlobalPrompts()->create([
            'company_id' => $company->id,
            'name' => 'RAG-Enhanced Knowledge Response',
            'prompt_content' => "When knowledge base information is available, always reference it specifically. Cite sources when providing information from documents. If the knowledge base contains relevant information, use it to provide accurate, detailed responses. Always mention which document or source the information comes from.",
            'description' => 'Enhanced prompt for responses using RAG and knowledge base',
            'prompt_type' => 'rag_enhanced',
            'is_active' => true,
            'settings' => [
                'temperature' => 0.5,
                'max_tokens' => 1500,
                'additional_instructions' => 'Prioritize accuracy over creativity when using knowledge base sources.',
            ],
        ]);
        
        $this->command->info('Sample global AI prompts created for admin.');
    }
}