@extends('portal.layout')
@section('title', 'My Workspaces')
@section('content')
<section class="page">
    <div class="page-header"><div><h1>My Workspaces</h1></div><a class="btn" href="{{ route('portal.workspaces.create') }}">Workspace အသစ်</a></div>
    <div class="pbr-workspace-grid">
        @forelse($workspaces as $workspace)
        <a class="surface pbr-workspace-card" href="{{ route('portal.workspaces.show', $workspace) }}">
            <span class="pbr-workspace-symbol">@include('portal.icon', ['icon'=>'folder'])</span>
            <h2>{{ $workspace->name }}</h2>
            <p>{{ \Illuminate\Support\Str::limit($workspace->description ?? '', 180) }}</p>
            <span class="pbr-workspace-caption">Owner · {{ $workspace->created_at->format('d M Y') }} @include('portal.icon', ['icon'=>'arrow-up-right'])</span>
        </a>
        @empty
        <div class="surface empty"><h2>Workspace မရှိသေးပါ။</h2><a class="btn" href="{{ route('portal.workspaces.create') }}">Workspace ဖန်တီးမယ်</a></div>
        @endforelse
    </div>
    <div class="portal-pagination">{{ $workspaces->links('portal.pagination') }}</div>
</section>
@endsection
