@extends('portal.layout')
@section('title', 'Business Plan · Version '.$revision->revision)
@include('portal.workspaces.business-plan.assets')
@section('content')
<section class="page pbr-plan-page">
    @include('portal.workspaces.business-plan.header')
    <div class="pbr-plan-state"><span class="pbr-plan-badge is-confirmed">Confirmed · Version {{ $revision->revision }}</span><span class="pbr-plan-meta">{{ $revision->created_at->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i') }}</span>@if($latest->id !== $revision->id)<a href="{{ route('portal.workspaces.business-plan.revision', [$workspace, $latest->id]) }}">လက်ရှိ Version {{ $latest->revision }}</a>@endif</div>
    @if($revision->based_on_revision)<p class="pbr-plan-meta">Version {{ $revision->based_on_revision }} ကို အခြေခံထားပါတယ်။</p>@endif
    @include('portal.workspaces.business-plan.summary')
    <section class="surface pbr-plan-next" aria-label="နောက်လုပ်ဆောင်ရန်">
        <div class="pbr-plan-actions">
            @if($source)<a class="btn" href="{{ route('portal.workspaces.knowledge.show', [$workspace, $source]) }}">Knowledge မှာ ကြည့်မယ်</a>@else<form method="post" action="{{ route('portal.workspaces.business-plan.knowledge', [$workspace, $revision->id]) }}">@csrf<button class="btn" type="submit">AI အတွက် ပြင်ဆင်မယ်</button></form>@endif
            <a class="btn secondary" href="{{ route('portal.workspaces.business-plan.edit', $workspace) }}">{{ $plan->draft !== null ? 'Draft ဆက်ရေးမယ်' : 'ပြန်ပြင်မယ်' }}</a>
        </div>
        <p class="pbr-plan-meta">{{ $source?->processing_status === 'ready' ? 'ချိတ်ထားတဲ့ Knowledge က Ready for AI ဖြစ်ပါပြီ။' : 'AI သုံးရန် Knowledge မှာ စစ်ဆေးပြီး Approve လုပ်ပေးပါ။' }}</p>
        <details class="quiet-details"><summary>Knowledge Version အကြောင်း</summary><p>Plan ကို ပြန်ပြင်တဲ့အခါ AI Knowledge ကို သီးခြား စစ်ဆေးနိုင်ပါတယ်။ ယခင် Plan ကို မသုံးတော့ရင် Knowledge မှာ Historical အဖြစ် ပြောင်းထားနိုင်ပါတယ်။</p></details>
        @if($source && $latest->id !== $revision->id)<p class="pbr-plan-meta">Version အသစ်အတွက် Knowledge ကို သီးခြား စစ်ဆေးနိုင်ပါတယ်။</p>@endif
    </section>
    @if($latest->id !== $revision->id)
    <form class="pbr-plan-actions" method="post" action="{{ route('portal.workspaces.business-plan.copy', [$workspace, $revision->id]) }}">@csrf<input type="hidden" name="version" value="{{ $plan->lock_version }}"><button class="btn secondary" type="submit">{{ $plan->draft !== null ? 'ဒီ Version နဲ့ လက်ရှိ Draft ကို အစားထိုးမယ်' : 'ဒီ Version ကို Draft အဖြစ် ယူမယ်' }}</button></form>
    @endif
</section>
@endsection
