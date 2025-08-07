<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalAIPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class GlobalAIPromptController extends Controller
{
    /**
     * Display admin settings page with global prompts
     */
    public function index()
    {
        $user = Auth::user();
        
        // Check if user is admin
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $prompts = GlobalAIPrompt::where('company_id', $user->company_id)
            ->with(['creator', 'updater'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/GlobalPrompts', [
            'prompts' => $prompts,
            'promptTypes' => [
                'general' => 'General',
                'rag_enhanced' => 'RAG Enhanced',
                'support' => 'Support',
                'sales' => 'Sales',
                'billing' => 'Billing',
            ],
        ]);
    }

    /**
     * Store a new global prompt
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'prompt_content' => 'required|string',
            'description' => 'nullable|string',
            'prompt_type' => 'required|in:general,rag_enhanced,support,sales,billing',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'settings.temperature' => 'nullable|numeric|min:0|max:2',
            'settings.max_tokens' => 'nullable|integer|min:1|max:4000',
            'settings.additional_instructions' => 'nullable|string',
        ]);

        // Deactivate other prompts of the same type if this one is active
        if ($validated['is_active'] ?? false) {
            GlobalAIPrompt::where('company_id', $user->company_id)
                ->where('prompt_type', $validated['prompt_type'])
                ->update(['is_active' => false]);
        }

        $prompt = GlobalAIPrompt::create([
            'company_id' => $user->company_id,
            'name' => $validated['name'],
            'prompt_content' => $validated['prompt_content'],
            'description' => $validated['description'] ?? null,
            'prompt_type' => $validated['prompt_type'],
            'is_active' => $validated['is_active'] ?? false,
            'settings' => $validated['settings'] ?? null,
            'created_by' => $user->id,
        ]);

        return redirect()->back()->with('success', 'Global AI prompt created successfully');
    }

    /**
     * Update an existing global prompt
     */
    public function update(Request $request, GlobalAIPrompt $prompt)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin() || $prompt->company_id !== $user->company_id) {
            abort(403, 'Unauthorized access');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'prompt_content' => 'required|string',
            'description' => 'nullable|string',
            'prompt_type' => 'required|in:general,rag_enhanced,support,sales,billing',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'settings.temperature' => 'nullable|numeric|min:0|max:2',
            'settings.max_tokens' => 'nullable|integer|min:1|max:4000',
            'settings.additional_instructions' => 'nullable|string',
        ]);

        // Deactivate other prompts of the same type if this one is active
        if ($validated['is_active'] ?? false) {
            GlobalAIPrompt::where('company_id', $user->company_id)
                ->where('prompt_type', $validated['prompt_type'])
                ->where('id', '!=', $prompt->id)
                ->update(['is_active' => false]);
        }

        $prompt->update([
            'name' => $validated['name'],
            'prompt_content' => $validated['prompt_content'],
            'description' => $validated['description'] ?? null,
            'prompt_type' => $validated['prompt_type'],
            'is_active' => $validated['is_active'] ?? false,
            'settings' => $validated['settings'] ?? null,
            'updated_by' => $user->id,
        ]);

        return redirect()->back()->with('success', 'Global AI prompt updated successfully');
    }

    /**
     * Delete a global prompt
     */
    public function destroy(GlobalAIPrompt $prompt)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin() || $prompt->company_id !== $user->company_id) {
            abort(403, 'Unauthorized access');
        }

        $prompt->delete();

        return redirect()->back()->with('success', 'Global AI prompt deleted successfully');
    }

    /**
     * Toggle prompt active status
     */
    public function toggleActive(GlobalAIPrompt $prompt)
    {
        $user = Auth::user();
        
        if (!$user->isAdmin() || $prompt->company_id !== $user->company_id) {
            abort(403, 'Unauthorized access');
        }

        // If activating, deactivate others of the same type
        if (!$prompt->is_active) {
            GlobalAIPrompt::where('company_id', $user->company_id)
                ->where('prompt_type', $prompt->prompt_type)
                ->where('id', '!=', $prompt->id)
                ->update(['is_active' => false]);
        }

        $prompt->update([
            'is_active' => !$prompt->is_active,
            'updated_by' => $user->id,
        ]);

        return redirect()->back()->with('success', 
            $prompt->is_active ? 'Prompt activated successfully' : 'Prompt deactivated successfully'
        );
    }
}