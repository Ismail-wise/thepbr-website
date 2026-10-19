@extends('portal.layout')
@section('title', $workspace->name.' · Partners')
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><h1>Partners</h1><p>{{ $workspace->name }} · PartnerDynamics only</p></div></div>
    @if(session('invitation_link'))
    <div class="surface pbr-invitation-created" role="status">
        <h2>Invitation link</h2><p>{{ session('invitation_email') }} အတွက်သာ ဖြစ်ပါတယ်။</p>
        <label for="invitation-link">Link ကူးပြီး ပို့ပေးပါ။</label><input id="invitation-link" readonly value="{{ session('invitation_link') }}" autocomplete="off">
        <p class="muted small">Email အလိုအလျောက် မပို့ထားပါ။ တစ်ကြိမ်သုံး · {{ \Carbon\Carbon::parse(session('invitation_expires_at'))->timezone(config('app.display_timezone'))->format('d M Y, H:i') }} အထိ သုံးနိုင်ပါတယ်။</p>
    </div>
    @endif
    <form class="surface portal-tool-form" method="post" action="{{ route('workspace-invitations.store', $workspace) }}" data-pbr-loading>
        @csrf
        <div class="field"><label for="partner-email">Partner email</label><input id="partner-email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></div>
        <details class="quiet-details"><summary>Partner ဝင်ခွင့်</summary><p>Admin approval မလိုပါ။ PartnerDynamics ကိုပဲ သုံးနိုင်ပါတယ်။ Workspace နဲ့ Student tools တွေကို မမြင်ရပါ။</p></details>
        <div class="page-actions"><button class="btn" type="submit">Invitation link ထုတ်မယ်</button></div>
    </form>
    <div class="pbr-partner-list" style="margin-top:28px">
        @forelse($memberships as $membership)
        <div class="surface pbr-partner-row">
            <div><strong>{{ $membership->invited_email }}</strong><p class="muted small">@if($membership->invitation_status === 'accepted') Accepted · Partner Dynamics only @elseif($membership->invitation_status === 'pending' && !$membership->isInvitationUsable()) Expired @else {{ ucfirst($membership->invitation_status) }} @endif</p></div>
            @if($membership->invitation_status === 'pending')
            <form method="post" action="{{ route('workspace-invitations.revoke', [$workspace, $membership]) }}">@csrf @method('delete')<button class="btn secondary sm" type="submit">ဖိတ်ကြားချက် ရုပ်သိမ်းမယ်</button></form>
            @elseif($membership->invitation_status === 'accepted')
            <form method="post" action="{{ route('workspace-members.destroy', [$workspace, $membership]) }}">@csrf @method('delete')<button class="btn danger sm" type="submit">Partner access ဖယ်မယ်</button></form>
            @endif
        </div>
        @empty
        <div class="surface empty"><p>Partner မဖိတ်ကြားရသေးပါ။</p></div>
        @endforelse
    </div>
    <div class="portal-pagination">{{ $memberships->links('portal.pagination') }}</div>
</section>
@endsection
