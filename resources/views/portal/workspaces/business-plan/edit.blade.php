@extends('portal.layout')
@section('title', 'Business Plan · '.$workspace->name)
@include('portal.workspaces.business-plan.assets')
@section('content')
@php
    $labels = \App\Services\Portal\BusinessPlanContent::FIELDS;
    $groups = \App\Services\Portal\BusinessPlanContent::GROUPS;
    $stages = \App\Services\Portal\BusinessPlanContent::STAGES;
@endphp
<section class="page pbr-plan-page">
    @include('portal.workspaces.business-plan.header')
    <div class="pbr-plan-state"><span class="pbr-plan-badge">{{ $plan?->draft !== null ? 'Draft' : ($latest ? 'Version '.$latest->revision.' မှ ပြန်ပြင်မယ်' : 'New plan') }}</span>@if($latest)<a href="{{ route('portal.workspaces.business-plan.revision', [$workspace, $latest->id]) }}">Confirmed Version {{ $latest->revision }}</a>@endif</div>
    <form method="post" action="{{ route('portal.workspaces.business-plan.save', $workspace) }}" class="pbr-plan-form">
        @csrf
        <input type="hidden" name="version" value="{{ old('version', $plan?->lock_version ?? 0) }}">
        <div class="surface pbr-plan-basics"><div class="form-grid">
            <div class="field"><label for="business_name">{{ $labels['business_name'] }} <span aria-hidden="true">*</span></label><input id="business_name" name="business_name" maxlength="160" value="{{ old('business_name', $values['business_name'] ?? '') }}" autocomplete="organization" aria-describedby="plan-minimum"></div>
            <div class="field"><label for="business_stage">{{ $labels['business_stage'] }}</label><select id="business_stage" name="business_stage"><option value="">ရွေးချယ်ရန်</option>@foreach($stages as $key=>$label)<option value="{{ $key }}" @selected(old('business_stage', $values['business_stage'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        </div><p id="plan-minimum" class="pbr-plan-meta">* ပါတဲ့ ၃ ချက် ဖြည့်ပြီးမှ Confirm လုပ်နိုင်ပါတယ်။ မပြီးသေးရင် Draft သိမ်းထားနိုင်ပါတယ်။</p></div>
        @foreach($groups as $heading=>$fields)
        @php($hasError = collect($fields)->contains(fn($field) => $errors->has($field)))
        <details class="surface pbr-plan-section" @if($loop->first || $hasError) open @endif>
            <summary>{{ $heading }}</summary>
            <div class="pbr-plan-section-fields">
                @foreach($fields as $field)
                <div class="field"><label for="{{ $field }}">{{ $labels[$field] }} @if(in_array($field, ['value_proposition', 'customer_segments'], true))<span aria-hidden="true">*</span>@endif</label><textarea id="{{ $field }}" name="{{ $field }}" rows="3" maxlength="3000">{{ old($field, $values[$field] ?? '') }}</textarea>@error($field)<small class="pbr-plan-error">{{ $message }}</small>@enderror</div>
                @endforeach
                @if($loop->last)<p class="pbr-plan-meta">BEP အတွက် ကိုယ်တွက်ထားပုံနဲ့ ခန့်မှန်းချက်ကို မှတ်သားထားနိုင်ပါတယ်။</p>@endif
            </div>
        </details>
        @endforeach
        <div class="pbr-plan-actions"><button class="btn secondary" type="submit" name="intent" value="save">Save Draft</button><button class="btn" type="submit" name="intent" value="review">သိမ်းပြီး ပြန်စစ်မယ်</button></div>
    </form>
</section>
@endsection
