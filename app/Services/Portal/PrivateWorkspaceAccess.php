<?php

namespace App\Services\Portal;

use App\Models\PartnershipWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PrivateWorkspaceAccess
{
    public function authorize(Request $request, PartnershipWorkspace $workspace): void
    {
        abort_unless(config('partner_portal.enabled'), 404);
        abort_unless($request->user()?->isStudent(), 403);
        abort_unless((int) $workspace->owner_user_id === (int) $request->user()->id, 404);
    }

    public function lock(PartnershipWorkspace $workspace): mixed
    {
        $lock = Cache::lock('private-workspace:'.$workspace->id, max(600, (int) config('pbr_ai.timeout', 180) * 3 + 60));
        abort_unless($lock->get(), 409, 'ဒီ Workspace မှာ လုပ်ဆောင်နေဆဲ ရှိပါတယ်။ ခဏနေရင် ထပ်စမ်းပါ။');

        return $lock;
    }
}
