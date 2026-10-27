@include('portal.workspaces.tabs')
<div class="page-header">
    <div><p class="pbr-plan-workspace">{{ $workspace->name }}</p><h1>Business Plan</h1></div>
    @unless(request()->routeIs('portal.workspaces.business-plan.history'))<a class="btn secondary" href="{{ route('portal.workspaces.business-plan.history', $workspace) }}">History</a>@endunless
</div>
