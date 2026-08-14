<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'is_admin',
    'role',
    'class_session_id',
    'account_status',
    'portal_access_expires_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'portal_access_expires_at' => 'datetime',
        ];
    }

    /**
     * Legacy relation kept temporarily while the live system is migrated.
     */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    /**
     * Legacy relation kept temporarily while access-code redemption is migrated.
     */
    public function usedAccessCode(): HasOne
    {
        return $this->hasOne(StudentAccessCode::class, 'used_by_user_id');
    }

    public function createdAccessCodes(): HasMany
    {
        return $this->hasMany(StudentAccessCode::class, 'created_by_user_id');
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(PartnershipWorkspace::class, 'owner_user_id');
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin || $this->role === 'admin';
    }

    public function isStudent(): bool
    {
        if ($this->relationLoaded('studentEnrollments')) {
            return $this->studentEnrollments->contains(
                fn (StudentEnrollment $enrollment): bool => $enrollment->isActive(),
            );
        }

        if ($this->studentEnrollments()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('access_expires_at')
                    ->orWhere('access_expires_at', '>', now());
            })
            ->exists()) {
            return true;
        }

        return $this->role === 'student'
            && $this->account_status === 'active'
            && ($this->portal_access_expires_at === null || $this->portal_access_expires_at->isFuture());
    }

    public function isPartner(): bool
    {
        return $this->hasAcceptedPartnerWorkspaceMembership();
    }

    public function hasActivePortalAccess(): bool
    {
        return $this->isAdmin() || $this->isStudent();
    }

    public function hasAcceptedWorkspaceMembership(): bool
    {
        return $this->workspaceMemberships()
            ->where('invitation_status', 'accepted')
            ->exists();
    }

    public function hasAcceptedPartnerWorkspaceMembership(): bool
    {
        return $this->workspaceMemberships()
            ->where('member_role', 'partner')
            ->where('invitation_status', 'accepted')
            ->exists();
    }

    public function canAccessBusinessOperatingSystem(): bool
    {
        return $this->isAdmin()
            || $this->isStudent()
            || $this->hasAcceptedPartnerWorkspaceMembership();
    }

    public function canUsePbrAiAdvisor(): bool
    {
        return $this->isAdmin() || $this->isStudent();
    }

    public function canAccessWorkspace(PartnershipWorkspace $workspace): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ((int) $workspace->owner_user_id === (int) $this->id) {
            return $this->isStudent();
        }

        return $this->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->where('member_role', 'partner')
            ->where('invitation_status', 'accepted')
            ->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }
}
