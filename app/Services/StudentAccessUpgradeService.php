<?php

namespace App\Services;

use App\Models\PartnershipWorkspace;
use App\Models\StudentAccessCode;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentAccessUpgradeService
{
    public function upgrade(User $user, string $rawAccessCode): User
    {
        return DB::transaction(function () use ($user, $rawAccessCode): User {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUser->hasActiveAccount()) {
                throw ValidationException::withMessages([
                    'access_code' => 'This account is inactive. Please contact the PBR team.',
                ]);
            }

            if ($lockedUser->isStudent()) {
                throw ValidationException::withMessages([
                    'access_code' => 'This account already has active Student Portal access.',
                ]);
            }

            $accessCode = StudentAccessCode::query()
                ->where('code_hash', StudentAccessCode::fingerprint($rawAccessCode))
                ->lockForUpdate()
                ->first();

            if (! $accessCode || ! $accessCode->isUsable()) {
                throw ValidationException::withMessages([
                    'access_code' => 'This access code is invalid, expired, disabled, or already used.',
                ]);
            }

            $classSession = $accessCode->classSession()
                ->lockForUpdate()
                ->first();

            if ($classSession && $classSession->capacity > 0 && $classSession->enrolled >= $classSession->capacity) {
                throw ValidationException::withMessages([
                    'access_code' => 'This class batch is already full. Please contact the PBR team.',
                ]);
            }

            $lockedUser->update([
                'role' => 'student',
                'class_session_id' => $accessCode->class_session_id,
                'account_status' => 'active',
                'portal_access_expires_at' => null,
            ]);

            StudentEnrollment::query()->updateOrCreate(
                [
                    'user_id' => $lockedUser->id,
                    'class_session_id' => $accessCode->class_session_id,
                ],
                [
                    'student_access_code_id' => $accessCode->id,
                    'status' => 'active',
                    'started_at' => now(),
                    'access_expires_at' => null,
                ],
            );

            $workspace = PartnershipWorkspace::query()->firstOrCreate(
                ['owner_user_id' => $lockedUser->id],
                [
                    'name' => $lockedUser->name.' — My PBR Workspace',
                    'business_name' => null,
                    'status' => 'active',
                ],
            );

            WorkspaceMember::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $lockedUser->id,
                ],
                [
                    'member_role' => 'owner',
                    'invitation_status' => 'accepted',
                    'invited_email' => $lockedUser->email,
                    'invitation_token_hash' => null,
                    'invited_by_user_id' => $lockedUser->id,
                    'invited_at' => now(),
                    'accepted_at' => now(),
                    'permissions' => null,
                ],
            );

            $accessCode->update([
                'status' => 'used',
                'used_by_user_id' => $lockedUser->id,
                'used_at' => now(),
            ]);

            if ($classSession) {
                $classSession->increment('enrolled');
            }

            return $lockedUser->fresh([
                'studentEnrollments.classSession',
                'ownedWorkspaces',
                'workspaceMemberships.workspace',
            ]);
        });
    }
}
