@extends('portal.layout')
@section('title', 'My Library')
@section('content')
<section class="page"><div class="page-header"><div><span class="eyebrow">MY LIBRARY</span><h1>မိမိ သိမ်းထားသော ရလဒ်များ</h1><p>မိမိ Account ဖြင့် သိမ်းထားသောရလဒ်တွေကို ပြန်ကြည့်နိုင်ပါတယ်။</p></div></div>
    @forelse($items as $item)<a class="saved-row surface" href="{{ route('portal.saved', $item) }}"><span class="tool-icon">@include('portal.icon', ['icon'=>'folder'])</span><span class="saved-info"><h3>{{ $item->title }}</h3><span class="meta">{{ $item->created_at->format('d M Y, H:i') }}</span></span><span>ဖွင့်ကြည့်မယ် →</span></a>@empty<div class="empty">ရလဒ် မသိမ်းရသေးပါ။</div>@endforelse
    <div class="portal-pagination">{{ $items->links() }}</div>
    @if($assessments->isNotEmpty())<h2 style="margin:30px 0 18px">PartnerDynamics</h2>@foreach($assessments as $assessment)<a class="saved-row surface" href="{{ route('partner-dynamics.result',$assessment) }}"><span class="tool-icon">@include('portal.icon', ['icon'=>'users'])</span><span class="saved-info"><h3>{{ $assessment->primary_profile }}</h3><span class="meta">{{ $assessment->completed_at?->format('d M Y') }}</span></span><span>မူလ Result →</span></a>@endforeach @endif
</section>
@endsection
