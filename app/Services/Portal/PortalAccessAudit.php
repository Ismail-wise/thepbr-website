<?php

namespace App\Services\Portal;

use App\Models\PortalAccessEvent;
use App\Models\User;

class PortalAccessAudit
{
    public function record(User $actor, User $account, string $action, array $before, ?int $days = null): void
    {
        if (! config('partner_portal.enabled')) {
            return;
        }

        PortalAccessEvent::query()->create([
            'user_id' => $account->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'days' => $days,
            'before_state' => $before,
            'after_state' => app(StudentPortalAccess::class)->summary($account->fresh()),
            'created_at' => now(),
        ]);
    }
}
