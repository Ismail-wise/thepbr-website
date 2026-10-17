<?php

namespace App\Services\Portal;

use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPortalAccess
{
    public function summary(User $user): array
    {
        if ($user->isAdmin()) {
            return ['status' => 'active', 'expires_at' => null, 'started_at' => null];
        }

        $enrollments = $user->studentEnrollments()->get();
        $active = $enrollments->filter(fn (StudentEnrollment $row): bool => $row->isActive());
        $lifetime = $active->contains(fn (StudentEnrollment $row): bool => $row->access_expires_at === null);
        $current = $active->sortByDesc('access_expires_at')->first();
        $last = $enrollments->sortByDesc('access_expires_at')->first();
        $legacy = $enrollments->isEmpty() && $user->role === 'student';

        $status = ! $user->hasActiveAccount() ? 'suspended' : ($user->isStudent() ? 'active' : (
            $enrollments->contains('status', 'revoked') ? 'suspended' : (
                $enrollments->isNotEmpty() || $legacy ? 'expired' : 'pending'
            )
        ));

        return [
            'status' => $status,
            'started_at' => $current?->started_at ?? $last?->started_at,
            'expires_at' => $lifetime ? null : ($current?->access_expires_at ?? ($legacy ? $user->portal_access_expires_at : $last?->access_expires_at)),
        ];
    }

    public function grantDays(User $actor, User $account, int $days): void
    {
        abort_unless($actor->isAdmin(), 403);
        if ($days < 1 || $days > 3650 || $account->isAdmin() || ! $account->hasActiveAccount()) {
            throw ValidationException::withMessages(['days' => 'Active Student Account နှင့် 1 မှ 3650 ရက်အတွင်း သတ်မှတ်ပါ။']);
        }

        DB::transaction(function () use ($actor, $account, $days): void {
            $locked = User::query()->lockForUpdate()->findOrFail($account->id);
            $before = $this->summary($locked);
            if ($before['status'] === 'active' && $before['expires_at'] === null) {
                throw ValidationException::withMessages(['days' => 'ဒီ Account မှာ သက်တမ်းမကန့်သတ်ထားသော ဝင်ခွင့်ရှိပြီးသားဖြစ်ပါတယ်။']);
            }

            $base = $before['status'] === 'active' ? $before['expires_at']->copy() : now();
            $row = $locked->studentEnrollments()->latest('id')->first();
            $values = [
                'status' => 'active',
                'started_at' => $before['status'] === 'active' ? ($before['started_at'] ?? now()) : now(),
                'access_expires_at' => $base->addDays($days),
            ];
            if ($row) {
                $row->update($values);
            } else {
                $locked->studentEnrollments()->create($values);
            }

            $this->record($actor, $locked, $before['status'] === 'active' ? 'extended' : 'activated', $before, $days);
        });
    }

    public function suspend(User $actor, User $account): void
    {
        abort_unless($actor->isAdmin(), 403);
        abort_if($account->isAdmin(), 422);
        DB::transaction(function () use ($actor, $account): void {
            $locked = User::query()->lockForUpdate()->findOrFail($account->id);
            $before = $this->summary($locked);
            // Revoke every entitlement so another class enrollment cannot bypass suspension.
            if ($locked->studentEnrollments()->exists()) {
                $locked->studentEnrollments()->update(['status' => 'revoked']);
            } else {
                // An authoritative revoked row also disables the legacy role fallback.
                $locked->studentEnrollments()->create(['status' => 'revoked', 'started_at' => now(), 'access_expires_at' => now()]);
            }
            $this->record($actor, $locked, 'suspended', $before);
        });
    }

    private function record(User $actor, User $account, string $action, array $before, ?int $days = null): void
    {
        DB::table('portal_access_events')->insert([
            'user_id' => $account->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'days' => $days,
            'before_state' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_state' => json_encode($this->summary($account->fresh()), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
