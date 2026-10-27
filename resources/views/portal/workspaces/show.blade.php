@extends('portal.layout')
@section('title', $workspace->name)
@include('portal.workspaces.business-plan.assets')
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><h1>{{ $workspace->name }}</h1><a class="btn secondary" href="{{ route('portal.workspaces.edit', $workspace) }}">ပြင်မယ်</a></div>
    @if($workspace->description)<div class="surface pbr-workspace-description"><p>{{ $workspace->description }}</p></div>@endif
    <section class="surface pbr-plan-overview" aria-labelledby="business-plan-title">
        <div class="pbr-plan-overview-copy"><span class="pbr-plan-app-icon">@include('portal.icon', ['icon'=>'checklist'])</span><div><h2 id="business-plan-title">Business Plan</h2><p class="pbr-plan-meta">@if($plan?->draft !== null) Draft ရှိပါတယ် @elseif($planLatest) Confirmed · Version {{ $planLatest->revision }} @else လုပ်ငန်းအစီအစဉ်ကို စရေးမယ် @endif</p>@if($plan?->draft !== null && $planLatest)<p class="pbr-plan-meta">Confirmed Version {{ $planLatest->revision }} ကို History မှာ ပြန်ကြည့်နိုင်ပါတယ်။</p>@endif</div></div>
        <a class="btn" href="{{ $plan?->draft !== null || !$planLatest ? route('portal.workspaces.business-plan.edit', $workspace) : route('portal.workspaces.business-plan.revision', [$workspace, $planLatest->id]) }}">{{ $plan?->draft !== null ? 'ဆက်ရေးမယ်' : ($planLatest ? 'ကြည့်မယ်' : 'စမယ်') }}</a>
    </section>
    <div class="workspace-shortcuts">
        <a class="surface" href="{{ route('portal.workspaces.ai', $workspace) }}">@include('portal.icon', ['icon'=>'chat']) PBR AI</a>
        <a class="surface" href="{{ route('portal.workspaces.knowledge.index', $workspace) }}">@include('portal.icon', ['icon'=>'folder']) Knowledge</a>
        <a class="surface" href="{{ route('portal.workspaces.partners', $workspace) }}">@include('portal.icon', ['icon'=>'users']) Partners</a>
    </div>
</section>
@endsection
