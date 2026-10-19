@extends('portal.layout')
@section('title', 'Knowledge · '.$workspace->name)
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><h1>Knowledge</h1><p>{{ $workspace->name }} · Owner သီးသန့်</p></div><a class="btn" href="{{ route('portal.workspaces.knowledge.create', $workspace) }}">@include('portal.icon', ['icon'=>'plus']) ထည့်မယ်</a></div>
    <div class="pbr-knowledge-list">
    @forelse($sources as $source)
        <a class="surface pbr-knowledge-card" href="{{ route('portal.workspaces.knowledge.show', [$workspace, $source]) }}"><div class="pbr-knowledge-card-top"><span class="pbr-knowledge-kind">{{ $source->kind === 'text' ? 'Text' : strtoupper(pathinfo($source->file_path ?? '', PATHINFO_EXTENSION)) }}</span><span class="small muted">{{ $source->updated_at->format('d M Y') }}</span></div><h2>{{ $source->title }}</h2><div class="pbr-knowledge-tags">@include('portal.workspaces.knowledge.status')</div><p class="muted">{{ \Illuminate\Support\Str::limit($source->reviewed_text ?: 'စာသား ပြန်စစ်ရန်', 100) }}</p></a>
    @empty
        <div class="surface pbr-knowledge-empty"><h2>Knowledge မရှိသေးပါ။</h2><a class="btn" href="{{ route('portal.workspaces.knowledge.create', $workspace) }}">@include('portal.icon', ['icon'=>'plus']) Text / Photo / File</a></div>
    @endforelse
    </div>
    @include('portal.pagination', ['paginator'=>$sources])
</section>
@endsection
