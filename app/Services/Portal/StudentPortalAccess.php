<?php

namespace App\Services\Portal;

use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPortalAccess
{
    public function summary(User $user): array
    {
        if ($user->isAdmin()) {
            return ['status' => 'active', 'expires_at' => null, 'started_at' => null];
        }

        $enrollments = $user->relationLoaded('studentEnrollments')
            ? $user->studentEnrollments
            : $user->studentEnrollments()->get();
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

    /** SQL counterpart of summary(), shared by Filament filters and counts. */
    public function queryForStatus(Builder $query, string $status): Builder
    {
        return $query->where(function (Builder $query) use ($status): void {
            if ($status === 'expiring') {
                $this->activeQuery($query);
                $query->where('is_admin', false)->where('role', '!=', 'admin')
                    ->whereDoesntHave('studentEnrollments', fn (Builder $query) => $query->where('status', 'active')
                        ->where(fn (Builder $query) => $query->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()->addDays(7))))
                    ->where(fn (Builder $query) => $query->has('studentEnrollments')
                        ->orWhere(fn (Builder $query) => $query->whereNotNull('portal_access_expires_at')->where('portal_access_expires_at', '<=', now()->addDays(7))));

                return;
            }

            if ($status === 'active') {
                $this->activeQuery($query);

                return;
            }

            if ($status === 'suspended') {
                $query->where('account_status', '!=', 'active')
                    ->orWhere(function (Builder $query): void {
                        $query->whereNot(fn (Builder $query) => $this->activeQuery($query))
                            ->whereHas('studentEnrollments', fn (Builder $query) => $query->where('status', 'revoked'));
                    });

                return;
            }

            $query->where('account_status', 'active')
                ->whereNot(fn (Builder $query) => $this->activeQuery($query))
                ->whereDoesntHave('studentEnrollments', fn (Builder $query) => $query->where('status', 'revoked'));

            if ($status === 'pending') {
                $query->doesntHave('studentEnrollments')->where('role', '!=', 'student');
            } elseif ($status === 'expired') {
                $query->where(fn (Builder $query) => $query->has('studentEnrollments')->orWhere('role', 'student'));
            } else {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function activeQuery(Builder $query): void
    {
        $query->where('account_status', 'active')->where(function (Builder $query): void {
            $query->where('is_admin', true)->orWhere('role', 'admin')
                ->orWhereHas('studentEnrollments', fn (Builder $query) => $query
                    ->where('status', 'active')
                    ->where(fn (Builder $query) => $query->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now())))
                ->orWhere(fn (Builder $query) => $query->doesntHave('studentEnrollments')
                    ->where('role', 'student')
                    ->where(fn (Builder $query) => $query->whereNull('portal_access_expires_at')->orWhere('portal_access_expires_at', '>', now())));
        });
    }

    public function grantDays(User $actor, User $account, int $days): void
    {
        abort_unless($actor->isAdmin(), 403);
        if ($days < 1 || $days > 3650) {
            throw ValidationException::withMessages(['days' => 'Active Student Account နှင့် 1 မှ 3650 ရက်အတွင်း သတ်မှတ်ပါ။']);
        }

        DB::transaction(function () use ($actor, $account, $days): void {
            $locked = User::query()->lockForUpdate()->findOrFail($account->id);
            if ($locked->isAdmin() || ! $locked->hasActiveAccount()) {
                throw ValidationException::withMessages(['days' => 'Active Student Account ကိုသာ ဝင်ခွင့်ပေးနိုင်ပါတယ်။']);
            }
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

            app(PortalAccessAudit::class)->record($actor, $locked, $before['status'] === 'active' ? 'extended' : 'activated', $before, $days);
        });
    }

    public function suspend(User $actor, User $account): void
    {
        abort_unless($actor->isAdmin(), 403);
        DB::transaction(function () use ($actor, $account): void {
            $locked = User::query()->lockForUpdate()->findOrFail($account->id);
            abort_if($locked->is_admin || $locked->role === 'admin', 422);
            $before = $this->summary($locked);
            // Revoke every entitlement so another class enrollment cannot bypass suspension.
            if ($locked->studentEnrollments()->exists()) {
                $locked->studentEnrollments()->update(['status' => 'revoked']);
            } else {
                // An authoritative revoked row also disables the legacy role fallback.
                $locked->studentEnrollments()->create(['status' => 'revoked', 'started_at' => now(), 'access_expires_at' => now()]);
            }
            app(PortalAccessAudit::class)->record($actor, $locked, 'suspended', $before);
        });
    }
}
