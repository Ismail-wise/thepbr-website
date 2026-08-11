@extends('layouts.student-portal')

@section('title', $workspace->business_name ?: $workspace->name)

@section('content')
@php
    $businessName = $workspace->business_name ?: $workspace->name;
    $stageMm = $workspace->business_stage === 'existing'
        ? 'ရှိပြီးသား Partnership Business ကို စီမံနေသည်'
        : 'Partnership Business အသစ် စီစဉ်နေသည်';
    $acceptedPartners = $workspace->memberships
        ->where('member_role', 'partner')
        ->where('invitation_status', 'accepted');
    $pendingInvitations = $workspace->memberships
        ->where('invitation_status', 'pending');
@endphp

<div class="pbr2-page">
    <section class="pbr2-hero">
        <div class="pbr2-hero-row">
            <div>
                <span class="pbr2-eyebrow">Business Control Center</span>
                <h1>{{ $businessName }}</h1>
                <p>{{ $stageMm }}။ ဒီနေရာကနေ Partner Alignment, Business Tools, Feasibility, Valuation နဲ့ Workspace Management ကို တစ်နေရာတည်းမှာ စီမံနိုင်ပါတယ်။</p>
                <div class="pbr2-meta">
                    <span class="pbr2-badge">{{ $workspace->currency_code ?? 'Currency မသတ်မှတ်ရသေး' }}</span>
                    <span class="pbr2-badge {{ $workspace->business_stage === 'existing' ? 'orange' : 'gray' }}">{{ $workspace->business_stage === 'existing' ? 'EXISTING BUSINESS' : 'NEW BUSINESS' }}</span>
                </div>
            </div>

            <div class="pbr2-actions">
                <a class="pbr2-btn secondary" href="{{ route('workspaces.index') }}">Business List</a>
                @if($canManageBusiness)
                    <a class="pbr2-btn" href="{{ route('workspaces.edit', $workspace) }}">Business Settings</a>
                @endif
            </div>
        </div>

        <div class="pbr2-metrics">
            <div class="pbr2-metric">
                <span>ချိတ်ဆက်ထားသော Partner</span>
                <strong>{{ $partnerCount }}</strong>
            </div>
            <div class="pbr2-metric">
                <span>Saved Tool Outputs</span>
                <strong>{{ $savedOutputCount }}</strong>
            </div>
            <div class="pbr2-metric">
                <span>နောက်ဆုံးပြင်ဆင်ချိန်</span>
                <strong style="font-size:14px;">{{ $workspace->updated_at?->diffForHumans() ?? 'မရှိသေး' }}</strong>
            </div>
        </div>
    </section>

    <section class="pbr2-section">
        <div class="pbr2-section-head">
            <div>
                <h2>Business Management</h2>
                <p>လိုအပ်တဲ့ Module ကိုရွေးပြီး တိုက်ရိုက်အသုံးပြုပါ။</p>
            </div>
        </div>

        <div class="pbr2-grid">
            <article class="pbr2-card feature">
                <div class="pbr2-icon">◎</div>
                <span class="pbr2-eyebrow">Partner Dynamics</span>
                <h3>Partner Alignment & Roles</h3>
                <p>Partner တစ်ယောက်ချင်းစီရဲ့ အားသာချက်၊ လုပ်ဆောင်ပုံ၊ ကွာခြားချက်နဲ့ သင့်တော်တဲ့ Role တွေကို နားလည်နိုင်ပါတယ်။</p>
                <div class="pbr2-actions">
                    <a class="pbr2-btn" href="{{ route('workspaces.partner-dynamics.show', $workspace) }}">Partner Alignment ကိုဖွင့်ရန်</a>
                </div>
            </article>

            <article class="pbr2-card feature">
                <div class="pbr2-icon">▦</div>
                <span class="pbr2-eyebrow">PBR Business Tools</span>
                <h3>Chapter-based Business Tools</h3>
                <p>Capital, Ownership, Profit Sharing, Governance, Exit နဲ့ Partnership Management အတွက် practical tools တွေကို အသုံးပြုပါ။</p>
                <div class="pbr2-actions">
                    <a class="pbr2-btn" href="{{ route('workspaces.tools.index', $workspace) }}">Business Tools ကိုဖွင့်ရန်</a>
                </div>
            </article>

            <article class="pbr2-card feature">
                <div class="pbr2-icon">✓</div>
                <span class="pbr2-eyebrow">Feasibility</span>
                <h3>လုပ်ငန်း / Project ဆုံးဖြတ်ချက်</h3>
                <p>Business အသစ်၊ Product အသစ်၊ Branch အသစ် သို့မဟုတ် Project အသစ်ကို လက်ရှိအခြေအနေမှာ လုပ်သင့်မလုပ်သင့် စစ်ဆေးပါ။</p>
                <div class="pbr2-actions">
                    <a class="pbr2-btn" href="{{ route('workspaces.feasibility.show', $workspace) }}">Feasibility စစ်ဆေးရန်</a>
                </div>
            </article>

            @if($workspace->isExistingPartnership())
                <article class="pbr2-card feature">
                    <div class="pbr2-icon">฿</div>
                    <span class="pbr2-eyebrow">Business Valuation</span>
                    <h3>Business တန်ဖိုး ခန့်မှန်းရန်</h3>
                    <p>Financial Data နဲ့ Valuation Methods အမျိုးမျိုးကိုသုံးပြီး Conservative, Base နဲ့ Optimistic Value Range ကိုတွက်ပါ။</p>
                    <div class="pbr2-actions">
                        <a class="pbr2-btn" href="{{ route('workspaces.valuation.show', $workspace) }}">Valuation Center ကိုဖွင့်ရန်</a>
                    </div>
                </article>
            @endif
        </div>
    </section>

    @if(session('invitation_link'))
        <section class="pbr2-section">
            <div class="pbr2-card">
                <span class="pbr2-eyebrow">Invitation Ready</span>
                <h2>Partner ဆီပို့ရန် Link အသင့်ဖြစ်ပါပြီ</h2>
                <p>ဒီ Link က single-use ဖြစ်ပါတယ်။ သင်ဖိတ်ချင်တဲ့ Partner ကိုပဲ ပို့ပါ။</p>
                <div class="pbr2-copy-box">{{ session('invitation_link') }}</div>
            </div>
        </section>
    @endif

    <section class="pbr2-section">
        <details class="pbr2-details">
            <summary>Partners & Invitations ကို စီမံရန်</summary>
            <div class="pbr2-details-body">
                <div class="pbr2-grid">
                    <div class="pbr2-card">
                        <span class="pbr2-eyebrow">ချိတ်ဆက်ထားသော Partner များ</span>
                        <h3>{{ $acceptedPartners->count() }} Partner</h3>
                        <div class="pbr2-divider"></div>
                        @forelse($acceptedPartners as $membership)
                            <div class="pbr2-data-row">
                                <span>{{ $membership->user?->name ?? $membership->invited_email }}</span>
                                <strong>Partner</strong>
                            </div>
                        @empty
                            <p>Partner မချိတ်ဆက်ရသေးပါ။</p>
                        @endforelse
                    </div>

                    @if($canManageInvitations)
                        <div class="pbr2-card">
                            <span class="pbr2-eyebrow">Quick Invitation</span>
                            <h3>Shareable Invitation Link</h3>
                            <p>Email ကြိုထည့်စရာမလိုဘဲ single-use Link တစ်ခုဖန်တီးပြီး Partner ဆီပို့နိုင်ပါတယ်။</p>
                            <form method="POST" action="{{ route('workspace-invitations.shareable.store', $workspace) }}">
                                @csrf
                                <button class="pbr2-btn" type="submit">Invitation Link ဖန်တီးရန်</button>
                            </form>
                        </div>
                    @endif
                </div>

                @if($canManageInvitations)
                    <div class="pbr2-grid" style="margin-top:16px;">
                        <div class="pbr2-card">
                            <span class="pbr2-eyebrow">Email ဖြင့်ဖိတ်ရန်</span>
                            <h3>Partner Email ထည့်ပါ</h3>
                            <form method="POST" action="{{ route('workspace-invitations.store', $workspace) }}">
                                @csrf
                                <div class="pbr2-field">
                                    <label for="email">Partner Email Address</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                                    @error('email')<small class="pbr2-error">{{ $message }}</small>@enderror
                                </div>
                                <button class="pbr2-btn" type="submit">Secure Invitation ဖန်တီးရန်</button>
                            </form>
                        </div>

                        <div class="pbr2-card">
                            <span class="pbr2-eyebrow">စောင့်ဆိုင်းနေသော Invitation</span>
                            <h3>{{ $pendingInvitations->count() }} Pending</h3>
                            <div class="pbr2-divider"></div>
                            @forelse($pendingInvitations as $invitation)
                                <div class="pbr2-data-row" style="align-items:center;">
                                    <span>
                                        @if(str_ends_with(strtolower((string) $invitation->invited_email), '@invite.thepbr.local'))
                                            Shareable Invitation Link
                                        @else
                                            {{ $invitation->invited_email }}
                                        @endif
                                    </span>
                                    <form method="POST" action="{{ route('workspace-invitations.revoke', [$workspace, $invitation]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="pbr2-btn ghost small" type="submit">ပယ်ဖျက်ရန်</button>
                                    </form>
                                </div>
                            @empty
                                <p>Pending Invitation မရှိပါ။</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </details>
    </section>
</div>
@endsection
