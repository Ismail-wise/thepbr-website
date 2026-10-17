<?php

namespace App\Http\Controllers;

use App\Filament\Resources\UserAccess\UserAccessResource;
use App\Models\PartnerDynamicsAssessment;
use App\Models\PortalSavedItem;
use App\Models\User;
use App\Services\Portal\StudentPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerPortalController extends Controller
{
    public function home(Request $request, StudentPortalAccess $access): View
    {
        return view('portal.home', ['access' => $access->summary($request->user())]);
    }

    public function access(Request $request, StudentPortalAccess $access): View|RedirectResponse
    {
        abort_unless(config('partner_portal.enabled'), 404);
        if ($request->user()->hasActivePortalAccess()) {
            return redirect()->route('portal.home');
        }

        return view('portal.access', ['access' => $access->summary($request->user())]);
    }

    public function accounts(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return redirect()->to(UserAccessResource::getUrl('index', [
            'tableSearch' => mb_substr(trim((string) $request->query('search', '')), 0, 100),
        ], panel: 'admin'));
    }

    public function grant(Request $request, User $account, StudentPortalAccess $access): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $validated = $request->validate(['days' => ['required', 'integer', 'between:1,3650']]);
        $access->grantDays($request->user(), $account, (int) $validated['days']);

        return redirect()->to(UserAccessResource::getUrl('index', panel: 'admin'))
            ->with('success', 'Portal ဝင်ခွင့်နှင့် သက်တမ်းကို သိမ်းပြီးပါပြီ။');
    }

    public function suspend(Request $request, User $account, StudentPortalAccess $access): RedirectResponse
    {
        $access->suspend($request->user(), $account);

        return redirect()->to(UserAccessResource::getUrl('index', panel: 'admin'))
            ->with('success', 'Portal ဝင်ခွင့်ကို ယာယီရပ်ထားပါပြီ။');
    }

    public function library(Request $request): View
    {
        $items = PortalSavedItem::query()->where('user_id', $request->user()->id)->latest('id')->paginate(20);
        $assessments = PartnerDynamicsAssessment::query()->where('user_id', $request->user()->id)
            ->where('status', 'completed')->latest('id')->get();

        return view('portal.library', compact('items', 'assessments'));
    }

    public function saved(Request $request, PortalSavedItem $item): View
    {
        abort_unless((int) $item->user_id === (int) $request->user()->id, 404);

        return view('portal.saved', compact('item'));
    }

    public function deleteSaved(Request $request, PortalSavedItem $item): RedirectResponse
    {
        abort_unless((int) $item->user_id === (int) $request->user()->id, 404);
        $item->delete();

        return redirect()->route('portal.library')->with('success', 'ရွေးထားသော ရလဒ်ကို ဖျက်ပြီးပါပြီ။');
    }
}
