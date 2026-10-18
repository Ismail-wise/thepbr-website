<nav class="pbr-workspace-tabs" aria-label="Workspace">
    <a href="{{ route('portal.workspaces.index') }}">@include('portal.icon', ['icon'=>'back']) Workspaces</a>
    <a href="{{ route('portal.workspaces.show', $workspace) }}" @if(request()->routeIs('portal.workspaces.show')) aria-current="page" @endif>Overview</a>
    <a href="{{ route('portal.workspaces.partners', $workspace) }}" @if(request()->routeIs('portal.workspaces.partners')) aria-current="page" @endif>Partners</a>
</nav>
