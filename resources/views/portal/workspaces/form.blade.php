@extends('portal.layout')
@section('title', $workspace ? 'Edit Workspace' : 'New Workspace')
@section('content')
<section class="page">
    <div class="page-header"><div><h1>{{ $workspace ? 'Workspace ပြင်မယ်' : 'Workspace အသစ်' }}</h1></div></div>
    <form class="surface portal-tool-form" method="post" action="{{ $workspace ? route('portal.workspaces.update', $workspace) : route('portal.workspaces.store') }}" data-pbr-loading>
        @csrf
        @if($workspace) @method('put') @endif
        <div class="field"><label for="workspace-name">Workspace နာမည်</label><input id="workspace-name" name="name" value="{{ old('name', $workspace?->name) }}" maxlength="160" required autofocus></div>
        <div class="field" style="margin-top:22px"><label for="workspace-description">လုပ်ငန်းအကြောင်း <span class="muted">(Optional)</span></label><textarea id="workspace-description" name="description" rows="4" maxlength="2000">{{ old('description', $workspace?->description) }}</textarea></div>
        <div class="page-actions"><button class="btn" type="submit">{{ $workspace ? 'သိမ်းမယ်' : 'Workspace ဖန်တီးမယ်' }}</button><a class="btn secondary" href="{{ $workspace ? route('portal.workspaces.show', $workspace) : route('portal.workspaces.index') }}">နောက်ပြန်</a></div>
    </form>
</section>
@endsection
