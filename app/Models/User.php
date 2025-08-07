<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'role',
        'department',
        'product_specializations',
        'language_skills',
        'is_active',
        'workload_capacity',
        'current_workload',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'product_specializations' => 'array',
            'language_skills' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function sentMessages()
    {
        return $this->hasMany(SentMessage::class, 'sent_by');
    }

    public function emailAccounts()
    {
        return $this->hasMany(EmailAccount::class, 'user_id');
    }

    public function emailDrafts()
    {
        return $this->hasMany(EmailDraft::class);
    }

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Global AI prompts created by this user
     */
    public function createdGlobalPrompts()
    {
        return $this->hasMany(GlobalAIPrompt::class, 'created_by');
    }

    /**
     * Global AI prompts updated by this user
     */
    public function updatedGlobalPrompts()
    {
        return $this->hasMany(GlobalAIPrompt::class, 'updated_by');
    }
}
