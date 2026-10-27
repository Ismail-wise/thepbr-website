@extends('portal.layout')
@section('title', 'Review Business Plan · '.$workspace->name)
@include('portal.workspaces.business-plan.assets')
@section('content')
<section class="page pbr-plan-page">
    @include('portal.workspaces.business-plan.header')
    <div class="pbr-plan-state"><span class="pbr-plan-badge">Draft · ပြန်စစ်ရန်</span><a href="{{ route('portal.workspaces.business-plan.edit', $workspace) }}">ပြန်ပြင်မယ်</a></div>
    @if($missing)<div class="surface pbr-plan-notice" role="status"><p>Confirm လုပ်ရန် ဖြည့်ဖို့ ကျန်နေပါတယ်။</p><ul>@foreach($missing as $label)<li>{{ $label }}</li>@endforeach</ul></div>@endif
    @include('portal.workspaces.business-plan.summary')
    <form method="post" action="{{ route('portal.workspaces.business-plan.confirm', $workspace) }}" class="surface pbr-plan-confirm">
        @csrf<input type="hidden" name="version" value="{{ $plan->lock_version }}">
        <label><input type="checkbox" name="reviewed" value="1" required @disabled((bool)$missing)> ဒီ Plan ကို စစ်ဆေးပြီးပါပြီ။</label>
        <p class="pbr-plan-meta">အတည်ပြုထားတဲ့ Version ကို History မှာ သိမ်းထားပါမယ်။ AI အတွက် သုံးရန် Knowledge မှာ သီးခြား Approve လုပ်နိုင်ပါတယ်။</p>
        <div class="pbr-plan-actions"><a class="btn secondary" href="{{ route('portal.workspaces.business-plan.edit', $workspace) }}">ပြန်ပြင်မယ်</a><button class="btn" type="submit" @disabled((bool)$missing)>Confirm</button></div>
    </form>
</section>
@endsection
