<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\GlobalAIPrompt;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'plans' => config('plans.plans'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name' => 'required|string|max:255',
            'plan' => 'required|string|in:'.implode(',', array_keys(config('plans.plans'))),
        ]);

        DB::transaction(function () use ($request, &$user) {
            // Create the company first
            $planConfig = config('plans.plans.'.$request->plan);
            $company = Company::create([
                'name' => $request->company_name,
                'plan' => $request->plan,
                'email_limit' => $planConfig['email_limit'],
                'subscription_plan' => 'starter',
                'is_active' => true,
            ]);

            // Create the user and assign to the company
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $company->id,
                'role' => 'admin',
                'is_active' => true,
            ]);
            
            // Create default global AI prompts for the new company
            $this->createDefaultGlobalPrompts($user, $company);
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
    
    /**
     * Create default global AI prompts for a new company
     */
    private function createDefaultGlobalPrompts(User $admin, Company $company): void
    {
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
            'created_by' => $admin->id,
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
            'created_by' => $admin->id,
        ]);
    }
}
