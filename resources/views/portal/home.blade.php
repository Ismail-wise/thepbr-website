@extends('portal.layout')
@section('title', 'Apps')
@section('content')
@php
    $apps = [
        ['PBR AI', 'မေးမြန်းဆွေးနွေးရန်', 'chat', route('portal.ai'), ''],
        ['PartnerDynamics', 'မိမိလုပ်ကိုင်ပုံကို နားလည်ရန်', 'users', route('partner-dynamics.index'), 'tone-gold'],
        ['Capital Calculator', 'ရင်းနှီးငွေ စမ်းတွက်ရန်', 'coin', route('portal.tool', 'capital'), ''],
        ['Share Simulator', 'ထည့်ဝင်မှု စမ်းနှိုင်းယှဉ်ရန်', 'chart', route('portal.tool', 'shares'), 'tone-blue'],
        ['Partnership Checklist', 'Partner နှင့် ဆွေးနွေးရန်', 'checklist', route('portal.tool', 'checklist'), 'tone-plum'],
        ['My Library', 'သိမ်းထားသည်များ ပြန်ကြည့်ရန်', 'folder', route('portal.library'), ''],
    ];
    if (auth()->user()->isAdmin()) $apps[] = ['Admin Portal', 'မူလ Filament မှ ဝင်ခွင့်နှင့် Content စီမံရန်', 'shield', route('filament.admin.pages.dashboard'), 'tone-gold'];
@endphp
<section class="launcher">
    <span class="eyebrow">YOUR PARTNERSHIP SPACE</span>
    <h1>မင်္ဂလာပါ၊ {{ auth()->user()->name }}။</h1>
    <p class="lead">ဒီနေ့ ဘာလုပ်ချင်ပါသလဲ?<br>အသုံးပြုချင်တဲ့ App ကို ရွေးပါ။</p>
    <div class="launcher-grid" aria-label="Partnership Apps">
        @foreach($apps as [$label,$description,$icon,$url,$tone])
        <a class="launcher-app {{ $tone }}" href="{{ $url }}"><span class="app-symbol">@include('portal.icon', ['icon'=>$icon])</span><strong>{{ $label }}</strong><small>{{ $description }}</small></a>
        @endforeach
    </div>
    <span class="access-pill">@include('portal.icon', ['icon'=>'shield'])
        @if(auth()->user()->isAdmin()) Administrator
        @elseif($access['expires_at']) အသုံးပြုခွင့် {{ $access['expires_at']->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i') }} အထိ
        @else သက်တမ်းမကန့်သတ်ထားသော ဝင်ခွင့် @endif
    </span>
    <div class="launcher-footer"><span>{{ auth()->user()->email }}</span><span>·</span><a href="{{ route('home') }}">Public Website</a>@if(auth()->user()->isAdmin())<span>·</span><a href="/admin">Admin Portal</a>@endif</div>
</section>
@endsection
