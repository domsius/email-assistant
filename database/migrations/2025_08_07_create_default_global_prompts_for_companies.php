<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Company;
use App\Models\GlobalAIPrompt;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all companies that don't have any global prompts
        $companies = Company::whereDoesntHave('globalPrompts')->get();
        
        foreach ($companies as $company) {
            // Find the first admin user for this company
            $adminUser = User::where('company_id', $company->id)
                ->where('role', 'admin')
                ->first();
                
            if (!$adminUser) {
                // If no admin, use the first user of the company
                $adminUser = User::where('company_id', $company->id)->first();
            }
            
            if ($adminUser) {
                // Create default general prompt with Lithuanian text
                GlobalAIPrompt::create([
                    'company_id' => $company->id,
                    'name' => 'Pagrindinis profesionalus tonas',
                    'prompt_content' => 'Tu privalai įdėti šį tekstą į atsakymą: Sveiki, appsas veikia, važiuojam toliau! Visada atsakyk lietuvių kalba. Būk mandagus ir profesionalus.',
                    'description' => 'Numatytasis promptas profesionaliam verslo bendravimui',
                    'prompt_type' => 'general',
                    'is_active' => true,
                    'settings' => [
                        'temperature' => 0.7,
                        'max_tokens' => 1000,
                    ],
                    'created_by' => $adminUser->id,
                ]);
                
                // Create RAG-enhanced prompt
                GlobalAIPrompt::create([
                    'company_id' => $company->id,
                    'name' => 'RAG pagerintas žinių atsakymas',
                    'prompt_content' => 'Visada atsakyk lietuvių kalba. Kai turima žinių bazės informacija, konkrečiai ją cituok. Nurodyk šaltinius, kai teiki informaciją iš dokumentų.',
                    'description' => 'Pagerintas promptas atsakymams naudojant RAG ir žinių bazę',
                    'prompt_type' => 'rag_enhanced',
                    'is_active' => true,
                    'settings' => [
                        'temperature' => 0.5,
                        'max_tokens' => 1500,
                        'additional_instructions' => 'Prioritetą teik tikslumui, o ne kūrybiškumui naudojant žinių bazės šaltinius.',
                    ],
                    'created_by' => $adminUser->id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't delete the prompts on rollback as they might have been modified
        // and we don't want to lose user data
    }
};