@extends('portal.layout')
@section('title', 'Account Access')
@section('content')
<section class="access-status" data-access-state="{{ $access['status'] }}">
    <span class="status-symbol">@include('portal.icon', ['icon'=>'shield'])</span>
    <span class="access-state {{ $access['status'] }}">{{ ucfirst($access['status']) }}</span>
    @if($access['status']==='pending')
        <h1>အတည်ပြုမှု စောင့်နေပါတယ်။</h1><p>Admin အတည်ပြုပြီးရင် စသုံးနိုင်ပါပြီ။</p>
    @elseif($access['status']==='expired')
        <h1>အသုံးပြုခွင့် သက်တမ်းကုန်သွားပါပြီ။</h1><p>သက်တမ်းတိုးရန် PBR Admin ကို ဆက်သွယ်ပါ။</p>
        @if($access['expires_at'])<p>ကုန်ဆုံးရက် — {{ $access['expires_at']->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i') }}</p>@endif
    @else
        <h1>ဝင်ခွင့်ကို ယာယီရပ်ထားပါတယ်။</h1><p>PBR Admin ကို ဆက်သွယ်ပါ။</p>
    @endif
    <div class="page-actions"><a class="btn" href="{{ route('portal.home') }}">ဝင်ခွင့် ပြန်စစ်မယ်</a><a class="btn secondary" href="{{ route('home') }}">Public Website</a></div>
    <p class="status-account">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
</section>
@endsection
