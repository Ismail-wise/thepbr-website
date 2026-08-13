@extends('layouts.student-portal')

@section('title', 'ကျွန်ုပ်၏ Business များ')

@section('content')
@php
    $owned = $workspaces->filter(fn ($workspace) => $user->isAdmin() || $workspace->owner_user_id === $user->id);
    $invited = $workspaces->filter(fn ($workspace) => ! $user->isAdmin() && $workspace->owner_user_id !== $user->id);
@endphp

<div class="pbr2-page">
    <section class="pbr2-hero">
        <div class="pbr2-hero-row">
            <div>
                <span class="pbr2-eyebrow">My Businesses</span>
                <h1>သင့် Partnership Business အားလုံးကို တစ်နေရာတည်းက စီမံပါ</h1>
                <p>Business တစ်ခုချင်းစီရဲ့ Partner၊ Capital၊ Ownership၊ Finance၊ Governance၊ Feasibility၊ Valuation နဲ့ Active Business Rules တွေကို သီးခြား Workspace အဖြစ် စနစ်တကျ စီမံနိုင်ပါတယ်။</p>
            </div>

            @if($canCreateBusiness)
                <a class="pbr2-btn" href="{{ route('workspaces.create') }}">+ Business အသစ်ထည့်ရန်</a>
            @endif
        </div>
    </section>

    <section class="pbr2-section">
        <div class="pbr2-section-head">
            <div>
                <h2>ကျွန်ုပ်ပိုင် Business များ</h2>
                <p>Owner အဖြစ် Operating System၊ Partner Access နဲ့ Business Rules ကို သင်စီမံနိုင်တဲ့ Workspace များ</p>
            </div>
        </div>

        <div class="pbr2-business-grid">
            @forelse($owned as $workspace)
                <article class="pbr2-card pbr2-business-card">
                    <div class="pbr2-meta">
                        <span class="pbr2-badge">OWNER</span>
                        <span class="pbr2-badge {{ $workspace->business_stage === 'existing' ? 'orange' : 'gray' }}">
                            {{ $workspace->business_stage === 'existing' ? 'ရှိပြီးသား Business' : 'Business အသစ် စီစဉ်နေသည်' }}
                        </span>
                    </div>

                    <h3 class="pbr2-business-name">{{ $workspace->business_name ?: $workspace->name }}</h3>

                    <div class="pbr2-data-row">
                        <span>အဓိက Currency</span>
                        <strong>{{ $workspace->currency_code ?? 'မသတ်မှတ်ရသေး' }}</strong>
                    </div>
                    <div class="pbr2-data-row">
                        <span>ချိတ်ဆက်ထားသော Partner</span>
                        <strong>{{ $workspace->acceptedMemberships->where('member_role', 'partner')->count() }}</strong>
                    </div>

                    <div class="pbr2-actions">
                        <a class="pbr2-btn" href="{{ route('workspaces.show', $workspace) }}">Business Control Center</a>
                        <a class="pbr2-btn secondary" href="{{ route('workspaces.tools.index', $workspace) }}">Operating System</a>
                        @if($workspace->owner_user_id === $user->id || $user->isAdmin())
                            <a class="pbr2-btn secondary" href="{{ route('workspaces.edit', $workspace) }}">Settings</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pbr2-empty">
                    <strong>ကိုယ်ပိုင် Business မရှိသေးပါ</strong><br>
                    Partnership Business တစ်ခုဖန်တီးပြီး actual business data နဲ့ PBR Operating System ကို စတင်အသုံးပြုနိုင်ပါတယ်။
                </div>
            @endforelse
        </div>
    </section>

    @if($invited->isNotEmpty())
        <section class="pbr2-section">
            <div class="pbr2-section-head">
                <div>
                    <h2>Partner အဖြစ် ဝင်ထားသော Business များ</h2>
                    <p>အခြား Owner တွေက သင့်ကို ဖိတ်ကြားထားပြီး အတည်ပြုထားတဲ့ shared business information ကို ကြည့်နိုင်တဲ့ Workspace များ</p>
                </div>
            </div>

            <div class="pbr2-business-grid">
                @foreach($invited as $workspace)
                    <article class="pbr2-card pbr2-business-card">
                        <div class="pbr2-meta">
                            <span class="pbr2-badge orange">PARTNER ACCESS</span>
                        </div>
                        <h3 class="pbr2-business-name">{{ $workspace->business_name ?: $workspace->name }}</h3>
                        <div class="pbr2-data-row"><span>Owner</span><strong>{{ $workspace->owner?->name ?? 'မသိရှိရသေး' }}</strong></div>
                        <div class="pbr2-data-row"><span>Currency</span><strong>{{ $workspace->currency_code ?? 'မသတ်မှတ်ရသေး' }}</strong></div>
                        <div class="pbr2-actions">
                            <a class="pbr2-btn" href="{{ route('workspaces.show', $workspace) }}">Business ကိုဖွင့်ရန်</a>
                            <a class="pbr2-btn secondary" href="{{ route('workspaces.tools.index', $workspace) }}">Active Rules ကြည့်ရန်</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="pbr2-section">
        <details class="pbr2-details">
            <summary>Partner Invitation Link ရှိပါသလား?</summary>
            <div class="pbr2-details-body">
                <p style="margin-top:0;color:var(--pbr2-muted);font-size:14px;">Partner ဆီကရထားတဲ့ Invitation Link ကို အောက်မှာထည့်ပြီး Business Workspace ကို ချိတ်ဆက်နိုင်ပါတယ်။</p>
                <form method="POST" action="{{ route('workspace-invitations.connect') }}">
                    @csrf
                    <div class="pbr2-field">
                        <label for="invitation_link">Invitation Link</label>
                        <input id="invitation_link" name="invitation_link" type="text" value="{{ old('invitation_link') }}" placeholder="https://thepbr.io/workspace-invitations/..." required>
                        @error('invitation_link')<small class="pbr2-error">{{ $message }}</small>@enderror
                    </div>
                    <button class="pbr2-btn" type="submit">Link ကိုစစ်ပြီး ချိတ်ဆက်ရန်</button>
                </form>
            </div>
        </details>
    </section>
</div>
@endsection
