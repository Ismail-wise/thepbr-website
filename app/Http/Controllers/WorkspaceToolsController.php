<?php

namespace App\Http\Controllers;

use App\Models\CourseChapter;
use App\Models\PartnershipWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceToolsController extends Controller
{
    public function index(
        Request $request,
        PartnershipWorkspace $workspace
    ): View {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        $workspace->load([
            'owner',
            'acceptedMemberships.user',
        ]);

        $businessStage = $workspace->business_stage;

        $chapters = CourseChapter::query()
            ->with([
                'tools' => function ($query) use ($businessStage) {
                    $query->orderBy('sort_order');

                    if ($businessStage === 'new') {
                        $query->where(
                            'supports_new_business',
                            true
                        );
                    }

                    if ($businessStage === 'existing') {
                        $query->where(
                            'supports_existing_business',
                            true
                        );
                    }
                },
            ])
            ->orderBy('chapter_number')
            ->get();

        $canManageContext =
            $request->user()->isAdmin()
            || $workspace->owner_user_id === $request->user()->id;

        $businessStages =
            PartnershipWorkspace::BUSINESS_STAGES;

        $currencies =
            PartnershipWorkspace::CURRENCIES;

        return view(
            'workspaces.tools.index',
            compact(
                'workspace',
                'chapters',
                'canManageContext',
                'businessStages',
                'currencies'
            )
        );
    }

    public function updateContext(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        abort_unless(
            $request->user()->canAccessWorkspace($workspace),
            403
        );

        abort_unless(
            $request->user()->isAdmin()
            || $workspace->owner_user_id === $request->user()->id,
            403
        );

        $validated = $request->validate([
            'business_stage' => [
                'required',
                Rule::in(
                    array_keys(
                        PartnershipWorkspace::BUSINESS_STAGES
                    )
                ),
            ],

            'currency_code' => [
                'required',
                Rule::in(
                    array_keys(
                        PartnershipWorkspace::CURRENCIES
                    )
                ),
            ],
        ]);

        $workspace->update($validated);

        return redirect()
            ->route(
                'workspaces.tools.index',
                $workspace
            )
            ->with(
                'success',
                'Partnership business settings saved.'
            );
    }
}
