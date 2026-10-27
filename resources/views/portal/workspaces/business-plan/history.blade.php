@extends('portal.layout')
@section('title', 'Business Plan History · '.$workspace->name)
@include('portal.workspaces.business-plan.assets')
@section('content')
<section class="page pbr-plan-page">
    @include('portal.workspaces.business-plan.header')
    <div class="pbr-plan-state"><h2>History</h2><a class="btn secondary" href="{{ route('portal.workspaces.business-plan.edit', $workspace) }}">{{ $plan?->draft !== null ? 'Draft ဆက်ရေးမယ်' : 'Plan ပြင်မယ်' }}</a></div>
    <div class="pbr-plan-history">
        @forelse($revisions as $revision)
        <a class="surface" href="{{ route('portal.workspaces.business-plan.revision', [$workspace, $revision->id]) }}"><span><strong>Version {{ $revision->revision }}</strong><span class="pbr-plan-meta">{{ $revision->content['business_name'] }}</span></span><span class="pbr-plan-meta">{{ $revision->created_at->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i') }}</span></a>
        @empty<div class="surface empty">အတည်ပြုထားတဲ့ Version မရှိသေးပါ။</div>@endforelse
    </div>
    <div class="portal-pagination">@include('portal.pagination', ['paginator'=>$revisions])</div>
</section>
@endsection
