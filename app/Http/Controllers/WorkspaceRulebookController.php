<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\PbrBusinessRulebookService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceRulebookController extends Controller
{
    public function show(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrBusinessRulebookService $rulebooks
    ): View {
        abort_unless(
            $request
                ->user()
                ->canAccessWorkspace(
                    $workspace
                ),
            403
        );

        $rulebook =
            $rulebooks->build(
                $request->user(),
                $workspace
            );

        return view(
            'workspaces.rulebook',
            compact(
                'workspace',
                'rulebook'
            )
        );
    }
}
