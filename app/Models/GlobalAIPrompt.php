<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalAIPrompt extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'global_ai_prompts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'prompt_content',
        'description',
        'is_active',
        'prompt_type',
        'settings',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the company that owns the global prompt.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the prompt.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the prompt.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get active prompts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get prompts by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('prompt_type', $type);
    }

    /**
     * Get the active global prompt for a company by type
     * First checks for platform-wide prompts (company_id = null), then company-specific
     */
    public static function getActivePromptForCompany(int $companyId, string $type = 'general')
    {
        // First check for platform-wide prompt (admin global prompt)
        $platformPrompt = self::whereNull('company_id')
            ->where('prompt_type', $type)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($platformPrompt) {
            return $platformPrompt;
        }
        
        // Fall back to company-specific prompt
        return self::where('company_id', $companyId)
            ->where('prompt_type', $type)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get the active RAG-enhanced prompt for a company
     */
    public static function getActiveRAGPromptForCompany(int $companyId)
    {
        return self::getActivePromptForCompany($companyId, 'rag_enhanced');
    }
    
    /**
     * Scope for platform-wide prompts
     */
    public function scopePlatformWide($query)
    {
        return $query->whereNull('company_id');
    }
    
    /**
     * Check if this is a platform-wide prompt
     */
    public function isPlatformWide(): bool
    {
        return $this->company_id === null;
    }
}