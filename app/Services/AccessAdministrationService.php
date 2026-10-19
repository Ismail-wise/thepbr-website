<?php

namespace App\Services;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\Portal\PortalAccessAudit;
use App\Services\Portal\StudentPortalAccess;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessAdministrationService
{
    public function activateAccount(User $actor, User $account): User
    {
        $this->assertAdmin($actor);
        return $this->audited($actor, $account, 'account_activated', function (User $account): User {
            $account->update(['account_status' => 'active']);

            return $account->fresh();
        });
    }

    public function deactivateAccount(User $actor, User $account): User
    {
        $this->assertAdmin($actor);

        if ((int) $actor->id === (int) $account->id) {
            throw ValidationException::withMessages([
                'account' => 'Administrators cannot deactivate their own signed-in account.',
            ]);
        }

        return $this->audited($actor, $account, 'account_deactivated', function (User $account): User {
            $account->update(['account_status' => 'inactive']);

            return $account->fresh();
        });
    }

    public function revokeStudentEntitlement(
        User $actor,
        StudentEnrollment $enrollment
    ): StudentEnrollment {
        $this->assertAdmin($actor);
        return $this->audited($actor, $enrollment->user, 'entitlement_revoked', function () use ($enrollment): StudentEnrollment {
            $enrollment->update([
                'status' => 'revoked',
                'access_expires_at' => now(),
            ]);

            return $enrollment->fresh();
        });
    }

    public function renewStudentEntitlement(
        User $actor,
        StudentEnrollment $enrollment
    ): StudentEnrollment {
        $this->assertAdmin($actor);

        return $this->audited($actor, $enrollment->user, 'renewed_year', function () use ($enrollment): StudentEnrollment {
            $enrollment->refresh();
            $reactivating = config('partner_portal.enabled') && ! $enrollment->isActive();
            $base = ! $reactivating && $enrollment->access_expires_at?->isFuture()
                ? $enrollment->access_expires_at->copy()
                : now();

            $enrollment->update([
                'status' => 'active',
                'started_at' => $reactivating ? now() : ($enrollment->started_at ?? now()),
                'access_expires_at' => $base->addYear(),
            ]);

            return $enrollment->fresh();
        });
    }

    public function grantLifetimeStudentEntitlement(
        User $actor,
        StudentEnrollment $enrollment
    ): StudentEnrollment {
        $this->assertAdmin($actor);
        return $this->audited($actor, $enrollment->user, 'granted_lifetime', function () use ($enrollment): StudentEnrollment {
            $enrollment->refresh()->update([
                'status' => 'active',
                'started_at' => $enrollment->started_at ?? now(),
                'access_expires_at' => null,
            ]);

            return $enrollment->fresh();
        });
    }

    public function grantOneYearStudentAccess(
        User $actor,
        User $account
    ): StudentEnrollment {
        return $this->createStudentEntitlement(
            $actor,
            $account,
            now()->addYear(),
        );
    }

    public function grantLifetimeStudentAccess(
        User $actor,
        User $account
    ): StudentEnrollment {
        return $this->createStudentEntitlement($actor, $account, null);
    }

    public function revokePartnerInvitation(
        User $actor,
        WorkspaceMember $membership
    ): WorkspaceMember {
        $this->assertAdmin($actor);
        $this->assertPartnerStatus($membership, 'pending');
        $membership->update([
            'invitation_status' => 'revoked',
            'invitation_token_hash' => null,
        ]);

        return $membership->fresh();
    }

    public function removePartnerAccess(
        User $actor,
        WorkspaceMember $membership
    ): WorkspaceMember {
        $this->assertAdmin($actor);
        $this->assertPartnerStatus($membership, 'accepted');
        $membership->update([
            'invitation_status' => 'removed',
            'invitation_token_hash' => null,
            'permissions' => null,
        ]);

        return $membership->fresh();
    }

    private function assertAdmin(User $actor): void
    {
        abort_unless($actor->isAdmin(), 403);
    }

    private function createStudentEntitlement(
        User $actor,
        User $account,
        mixed $accessExpiresAt
    ): StudentEnrollment {
        $this->assertAdmin($actor);

        return $this->audited($actor, $account, $accessExpiresAt === null ? 'granted_lifetime' : 'granted_year', function (User $account) use ($accessExpiresAt): StudentEnrollment {
            if (! $account->hasActiveAccount()) {
                throw ValidationException::withMessages([
                    'account' => 'Activate the account before granting Student access.',
                ]);
            }

            if ($account->studentEnrollments()->exists()) {
                throw ValidationException::withMessages([
                    'account' => 'This account already has an authoritative Student entitlement. Manage it from Student Entitlements.',
                ]);
            }

            // Moving legacy access into an authoritative enrollment must keep
            // its remaining term, just like the portal's custom-day extension.
            if (config('partner_portal.enabled') && $accessExpiresAt !== null && $account->isStudent()) {
                if ($account->portal_access_expires_at === null) {
                    throw ValidationException::withMessages([
                        'account' => 'This account already has lifetime Student access.',
                    ]);
                }

                $accessExpiresAt = $account->portal_access_expires_at->copy()->addYear();
            }

            return $account->studentEnrollments()->create([
                'class_session_id' => null,
                'student_access_code_id' => null,
                'status' => 'active',
                'started_at' => now(),
                'access_expires_at' => $accessExpiresAt,
            ]);
        });
    }

    private function audited(User $actor, User $account, string $action, Closure $change): mixed
    {
        return DB::transaction(function () use ($actor, $account, $action, $change): mixed {
            $locked = User::query()->lockForUpdate()->findOrFail($account->id);
            $before = config('partner_portal.enabled')
                ? app(StudentPortalAccess::class)->summary($locked)
                : [];
            $result = $change($locked);
            app(PortalAccessAudit::class)->record($actor, $locked, $action, $before);

            return $result;
        });
    }

    private function assertPartnerStatus(
        WorkspaceMember $membership,
        string $status
    ): void {
        if (
            $membership->member_role !== 'partner'
            || $membership->invitation_status !== $status
        ) {
            throw ValidationException::withMessages([
                'membership' => 'This membership is not eligible for that access action.',
            ]);
        }
    }
}
