@extends('layouts.student-portal')

@section('title', 'My Businesses')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">My Businesses</span>
            <h1>Business Workspaces</h1>
            <p>ကိုယ်ပိုင် Business တွေနဲ့ Partner အဖြစ် ဖိတ်ကြားထားတဲ့ Business တွေကို ဒီနေရာကနေ စီမံနိုင်ပါတယ်။</p>
            @if($canCreateBusiness)
                <a class="portal-button" href="{{ route('workspaces.create') }}">+ Add New Business</a>
            @endif
        </div>

        <div class="auth-card">
            <span class="portal-kicker">Partnership Invitation</span>
            <h2>Invitation Link ရှိပါသလား?</h2>
            <p>Partner ဆီကရထားတဲ့ Invitation Link ကို ဒီနေရာမှာထည့်ပြီး Workspace ချိတ်ဆက်နိုင်ပါတယ်။</p>
            <form method="POST" action="{{ route('workspace-invitations.connect') }}">
                @csrf
                <div class="field">
                    <label for="invitation_link">Invitation Link</label>
                    <input id="invitation_link" name="invitation_link" type="text" value="{{ old('invitation_link') }}" placeholder="https://thepbr.io/workspace-invitations/..." required>
                    @error('invitation_link')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <button class="portal-button" type="submit">Check & Connect Workspace</button>
            </form>
        </div>

        <div class="auth-card">
            <span class="portal-kicker">Available Businesses</span>
            @forelse($workspaces as $workspace)
                <div class="auth-note">
                    <strong>{{ $workspace->business_name ?: $workspace->name }}</strong><br>
                    @if($workspace->owner_user_id === $user->id)
                        OWNED BUSINESS
                    @elseif($user->isAdmin())
                        ADMIN ACCESS
                    @else
                        INVITED BUSINESS
                    @endif
                    <br><br>
                    <strong>Stage:</strong> {{ \App\Models\PartnershipWorkspace::BUSINESS_STAGES[$workspace->business_stage] ?? 'Not configured' }}<br>
                    <strong>Currency:</strong> {{ $workspace->currency_code ?? 'Not set' }}<br>
                    <strong>Owner:</strong> {{ $workspace->owner?->name ?? 'Unknown' }}<br><br>
                    <a href="{{ route('workspaces.show', $workspace) }}">Open Business →</a>
                </div>
            @empty
                <div class="auth-note">Business Workspace မရှိသေးပါ။ Student Account ဖြစ်ရင် <strong>Add New Business</strong> ကိုနှိပ်ပြီး စတင်နိုင်ပါတယ်။</div>
            @endforelse
        </div>
    </div>
</section>
@endsection
