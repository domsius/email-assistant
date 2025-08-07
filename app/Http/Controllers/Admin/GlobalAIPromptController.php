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

        // Platform admin (role = 'admin') sees platform-wide prompts
        // Company admin sees their company prompts
        if ($user->role === 'admin') {
            // Platform admin - show platform-wide prompts (company_id = null)
            $prompts = GlobalAIPrompt::whereNull('company_id')
                ->with(['creator', 'updater'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // Company admin - show company-specific prompts
            $prompts = GlobalAIPrompt::where('company_id', $user->company_id)
                ->with(['creator', 'updater'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return Inertia::render('Admin/GlobalPrompts', [
            'prompts' => $prompts,
            'promptTypes' => [
                'general' => 'General',
                'rag_enhanced' => 'RAG Enhanced',
                'support' => 'Support',
                'sales' => 'Sales',
                'billing' => 'Billing',
            ],
            'isPlatformAdmin' => $user->role === 'admin',
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

        // Determine if this is a platform-wide or company-specific prompt
        $companyId = $user->role === 'admin' ? null : $user->company_id;
        
        // Deactivate other prompts of the same type if this one is active
        if ($validated['is_active'] ?? false) {
            $query = GlobalAIPrompt::where('prompt_type', $validated['prompt_type']);
            
            if ($companyId === null) {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $companyId);
            }
            
            $query->update(['is_active' => false]);
        }

        $prompt = GlobalAIPrompt::create([
            'company_id' => $companyId, // null for platform-wide, company_id for company-specific
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
        
        // Platform admin can edit platform prompts, company admin can edit company prompts
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }
        
        if ($user->role === 'admin') {
            // Platform admin can only edit platform-wide prompts
            if ($prompt->company_id !== null) {
                abort(403, 'Platform admin can only edit platform-wide prompts');
            }
        } else {
            // Company admin can only edit their company's prompts
            if ($prompt->company_id !== $user->company_id) {
                abort(403, 'Unauthorized access');
            }
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
            $query = GlobalAIPrompt::where('prompt_type', $validated['prompt_type'])
                ->where('id', '!=', $prompt->id);
            
            if ($prompt->company_id === null) {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $prompt->company_id);
            }
            
            $query->update(['is_active' => false]);
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
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }
        
        if ($user->role === 'admin') {
            // Platform admin can only delete platform-wide prompts
            if ($prompt->company_id !== null) {
                abort(403, 'Platform admin can only delete platform-wide prompts');
            }
        } else {
            // Company admin can only delete their company's prompts
            if ($prompt->company_id !== $user->company_id) {
                abort(403, 'Unauthorized access');
            }
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
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }
        
        if ($user->role === 'admin') {
            // Platform admin can only toggle platform-wide prompts
            if ($prompt->company_id !== null) {
                abort(403, 'Platform admin can only toggle platform-wide prompts');
            }
        } else {
            // Company admin can only toggle their company's prompts
            if ($prompt->company_id !== $user->company_id) {
                abort(403, 'Unauthorized access');
            }
        }

        // If activating, deactivate others of the same type
        if (!$prompt->is_active) {
            $query = GlobalAIPrompt::where('prompt_type', $prompt->prompt_type)
                ->where('id', '!=', $prompt->id);
                
            if ($prompt->company_id === null) {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $prompt->company_id);
            }
            
            $query->update(['is_active' => false]);
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