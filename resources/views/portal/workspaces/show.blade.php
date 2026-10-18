@extends('portal.layout')
@section('title', $workspace->name)
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><span class="eyebrow">WORKSPACE OVERVIEW</span><h1>{{ $workspace->name }}</h1><p>သင်ပိုင်ဆိုင်သော Workspace</p></div><a class="btn secondary" href="{{ route('portal.workspaces.edit', $workspace) }}">ပြင်ဆင်မယ်</a></div>
    <div class="surface pbr-workspace-description"><h2>လုပ်ငန်းအကြောင်း</h2><p>{{ $workspace->description ?: 'လုပ်ငန်းအကျဉ်းချုပ်ကို ပြင်ဆင်မယ် မှ ထည့်နိုင်ပါတယ်။' }}</p></div>
    <div class="surface" style="margin-top:22px"><h2>Partners ဖိတ်ကြားမယ်</h2><p class="muted">ဖိတ်ကြားခံရသူက Partner Dynamics သာ အသုံးပြုနိုင်ပါတယ်။ Student ဝင်ခွင့်အတွက် သီးခြား Admin approval လိုအပ်ပါတယ်။</p><div class="page-actions"><a class="btn" href="{{ route('portal.workspaces.partners', $workspace) }}">Partners စီမံမယ်</a><a class="btn secondary" href="{{ route('partner-dynamics.index') }}">ကျွန်ုပ်၏ Partner Dynamics</a></div></div>
</section>
@endsection
