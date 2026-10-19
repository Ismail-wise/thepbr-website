@extends('portal.layout')
@section('title', $workspace->name)
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><h1>{{ $workspace->name }}</h1><a class="btn secondary" href="{{ route('portal.workspaces.edit', $workspace) }}">ပြင်မယ်</a></div>
    @if($workspace->description)<div class="surface pbr-workspace-description"><p>{{ $workspace->description }}</p></div>@endif
    <div class="workspace-shortcuts">
        <a class="surface" href="{{ route('portal.workspaces.ai', $workspace) }}">@include('portal.icon', ['icon'=>'chat']) PBR AI</a>
        <a class="surface" href="{{ route('portal.workspaces.knowledge.index', $workspace) }}">@include('portal.icon', ['icon'=>'folder']) Knowledge</a>
        <a class="surface" href="{{ route('portal.workspaces.partners', $workspace) }}">@include('portal.icon', ['icon'=>'users']) Partners</a>
    </div>
</section>
@endsection
