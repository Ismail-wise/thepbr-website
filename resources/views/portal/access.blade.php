@extends('portal.layout')
@section('title', 'Account Access')
@section('content')
<section class="access-status" data-access-state="{{ $access['status'] }}">
    <span class="status-symbol">@include('portal.icon', ['icon'=>'shield'])</span>
    <span class="access-state {{ $access['status'] }}">{{ ucfirst($access['status']) }}</span>
    @if($access['status']==='pending')
        <h1>အတည်ပြုမှု စောင့်နေပါတယ်။</h1><p>သင့် Account ကို ဖွင့်ပြီးပါပြီ။ Admin က Portal ဝင်ခွင့်နှင့် သက်တမ်းကို သတ်မှတ်ပေးပြီးနောက် Apps တွေကို အသုံးပြုနိုင်ပါမယ်။</p>
    @elseif($access['status']==='expired')
        <h1>အသုံးပြုခွင့် သက်တမ်းကုန်သွားပါပြီ။</h1><p>သက်တမ်းတိုးရန် PBR Admin ကို ဆက်သွယ်ပါ။ သင့် Chat၊ PartnerDynamics Result နှင့် သိမ်းထားသောရလဒ်များကို ဆက်ထိန်းထားပါတယ်။</p>
        @if($access['expires_at'])<p>ကုန်ဆုံးရက် — {{ $access['expires_at']->format('d M Y, H:i') }}</p>@endif
    @else
        <h1>ဝင်ခွင့်ကို ယာယီရပ်ထားပါတယ်။</h1><p>သင့် Portal ဝင်ခွင့်ကို PBR Admin နှင့် ပြန်စစ်ပါ။ သိမ်းထားသော အချက်အလက်တွေကို ဆက်ထိန်းထားပါတယ်။</p>
    @endif
    <div class="page-actions"><a class="btn" href="{{ route('portal.home') }}">ဝင်ခွင့် ပြန်စစ်မယ်</a><a class="btn secondary" href="{{ route('home') }}">Public Website</a></div>
    <p class="status-account">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
</section>
@endsection
