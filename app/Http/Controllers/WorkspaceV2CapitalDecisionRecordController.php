<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Services\PbrTools\PbrV2CapitalDecisionRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WorkspaceV2CapitalDecisionRecordController extends Controller
{
    public function __construct(
        private readonly PbrV2CapitalDecisionRecordService $decisionRecord
    ) {
    }

    public function store(
        Request $request,
        PartnershipWorkspace $workspace
    ): RedirectResponse {
        $record = $this->decisionRecord
            ->recordCurrent(
                $request->user(),
                $workspace
            );

        return redirect()
            ->route(
                'workspaces.tools.v2.capital.show',
                $workspace
            )
            ->with(
                'status',
                'Capital Plan revision '
                .$record['source']['revision']
                .' ရဲ့ Capital Decision Record ကို append-only record အဖြစ် မှတ်တမ်းတင်ပြီးပါပြီ။'
            );
    }
}
