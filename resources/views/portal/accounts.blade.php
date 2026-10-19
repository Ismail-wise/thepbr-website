@extends('portal.layout')
@section('title', 'Accounts & Access')
@section('content')
<section class="page">
    <div class="page-header"><div><span class="eyebrow">ACCOUNTS & ACCESS</span><h1>Account ဝင်ခွင့် စီမံမယ်။</h1><p>User ကိုယ်တိုင် Account ဖွင့်ပြီး Admin က ဝင်ခွင့်နှင့် သက်တမ်းကို စီမံပေးနိုင်ပါတယ်။</p></div></div>
    <form method="get" class="portal-search"><label class="sr-only" for="search">User ရှာရန်</label><input id="search" name="search" value="{{ $search }}" maxlength="100" placeholder="နာမည် သို့မဟုတ် Email"><button class="btn secondary">ရှာမယ်</button></form>
    <div class="surface access-admin-table"><table class="data-table"><thead><tr><th>Account</th><th>ဝင်ခွင့်</th><th>ကုန်ဆုံးရက်</th><th>စီမံရန်</th></tr></thead><tbody>
    @foreach($accounts as $account)
        @php($state = $access->summary($account))
        <tr><td class="account-cell"><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small><small>{{ $account->phone }}</small></td>
        <td><span class="access-state {{ $state['status'] }}">{{ $account->isAdmin() ? 'Admin' : ucfirst($state['status']) }}</span></td>
        <td class="date-cell">{{ $state['expires_at']?->copy()->timezone(config('app.display_timezone'))->format('d M Y, H:i') ?? ($state['status']==='active' ? 'သက်တမ်းမကန့်သတ်' : '—') }}</td>
        <td>@if(!$account->isAdmin() && $account->hasActiveAccount())
            @if($state['status']!=='active' || $state['expires_at'])
            <form method="post" action="{{ route('portal.grant', $account) }}" class="grant-form">@csrf<label class="sr-only" for="days-{{ $account->id }}">ရက်အရေအတွက်</label><input id="days-{{ $account->id }}" name="days" type="number" min="1" max="3650" value="90" required><span>ရက်</span><button class="btn secondary sm">{{ $state['status']==='active' ? 'သက်တမ်းတိုးမယ်' : 'ဝင်ခွင့်ပေးမယ်' }}</button></form>
            @endif
            @if($state['status']==='active')<form method="post" action="{{ route('portal.suspend', $account) }}" class="suspend-form">@csrf<button class="btn ghost sm">ဝင်ခွင့် ယာယီရပ်မယ်</button></form>@endif
        @elseif(!$account->hasActiveAccount())<small>Account ပြန်ဖွင့်ရန် Admin Portal ကို သုံးပါ။</small>@endif</td></tr>
    @endforeach
    </tbody></table></div>
    <div class="portal-pagination">@include('portal.pagination', ['paginator'=>$accounts])</div>
    <p class="small muted">Active ဝင်ခွင့်ကို တိုးရင် လက်ရှိကုန်ဆုံးရက်မှ ဆက်ပေါင်းပါတယ်။ Pending / Expired / Suspended Account အတွက် ဝင်ခွင့်ပေးသည့်အချိန်မှ စတွက်ပါတယ်။</p>
</section>
@endsection
