@extends('layouts.student-portal')

@section('title', 'Partner Roster')

@section('content')
<section class="pbr-os-page">
    <div class="portal-wrap pbr-os-wrap">
        <nav class="pbr-os-breadcrumb">
            <a href="{{ route('workspaces.show', $workspace) }}">Business Control Center</a>
            <span>›</span>
            <a href="{{ route('workspaces.tools.index', $workspace) }}">10-Chapter System</a>
            <span>›</span>
            <span>Partner Roster</span>
        </nav>

        <header class="pbr-os-hero">
            <div class="pbr-os-hero-copy">
                <div class="pbr-os-kickers">
                    <span class="pbr-os-chapter-pill">Shared Partner Data</span>
                    <span class="pbr-os-type-pill">One Business · One Roster</span>
                </div>
                <h1>Partner Roster</h1>
                <p class="pbr-os-en-title">Partner names and working context shared across Chapters 1–10</p>
                <p class="pbr-os-purpose">
                    Partner အမည်ကို Tool တစ်ခုချင်းစီမှာ ထပ်ခါထပ်ခါရိုက်စရာမလိုအောင် ဒီ Roster ကို source အဖြစ်သုံးပါတယ်။
                    PBR account နဲ့ connect လုပ်ထားတဲ့ Owner/Partner တွေကို automatically sync လုပ်ပြီး၊ မ invite ရသေးတဲ့ future partner ကို Planned Partner အဖြစ်ကြိုထည့်နိုင်ပါတယ်။
                </p>
            </div>
            <aside class="pbr-os-business-context">
                <span>Current Business</span>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <div>
                    <small>{{ $profiles->where('status', 'active')->count() }} Active</small>
                    <small>{{ $profiles->where('status', 'planned')->count() }} Planned</small>
                </div>
            </aside>
        </header>

        <div class="pbr-os-layout">
            <aside class="pbr-os-sidebar">
                <div class="pbr-os-side-card">
                    <span class="pbr-os-side-label">Why this matters</span>
                    <ol class="pbr-os-steps">
                        <li class="active"><span>1</span><div><b>တစ်ယောက်တည်းအဖြစ်သတ်မှတ်</b><small>Partner identity ကို consistent ထားမယ်</small></div></li>
                        <li><span>2</span><div><b>Tools ကို Prefill</b><small>Ownership, contribution, profit, voting</small></div></li>
                        <li><span>3</span><div><b>Invite နဲ့ Connect</b><small>Real PBR account ကို membership flow နဲ့ချိတ်</small></div></li>
                        <li><span>4</span><div><b>Agreed Rules</b><small>Approved outputs က business source-of-truth ဖြစ်မယ်</small></div></li>
                    </ol>
                </div>
            </aside>

            <main class="pbr-os-main">
                <section class="pbr-os-panel">
                    <div class="pbr-os-panel-head">
                        <div>
                            <span class="portal-kicker">Current Roster</span>
                            <h2>{{ $profiles->count() }} People / Planned Partners</h2>
                            <p>Linked account ကိုဒီနေရာကနေဖျက်လို့မရပါ။ Membership changes ကို Business invitation/member flow ကနေ manage လုပ်ရပါမယ်။</p>
                        </div>
                    </div>

                    <div class="pbr-roster-grid">
                        @forelse($profiles as $profile)
                            @php $profileData = $profile->profile_data ?? []; @endphp
                            <article class="pbr-roster-card {{ $profile->status }}">
                                <div class="pbr-roster-card-head">
                                    <div class="pbr-roster-avatar">{{ mb_strtoupper(mb_substr($profile->display_name, 0, 1)) }}</div>
                                    <div>
                                        <strong>{{ $profile->display_name }}</strong>
                                        <span>{{ $profile->user_id ? 'Connected PBR Account' : 'Planned Partner' }}</span>
                                    </div>
                                    <span class="pbr-roster-status">{{ ucfirst($profile->status) }}</span>
                                </div>

                                <form method="POST" action="{{ route('workspaces.partner-roster.update', [$workspace, $profile]) }}" class="pbr-roster-form">
                                    @csrf
                                    @method('PUT')
                                    <label>
                                        <span>Partner အမည်</span>
                                        <input type="text" name="display_name" value="{{ $profile->display_name }}" maxlength="160" required {{ $profile->user_id ? 'readonly' : '' }}>
                                    </label>
                                    <label>
                                        <span>Role / Working Title</span>
                                        <input type="text" name="role_title" value="{{ $profileData['role_title'] ?? '' }}" maxlength="160" placeholder="ဥပမာ Operations Partner">
                                    </label>
                                    <label class="wide">
                                        <span>Contribution / Responsibility Note</span>
                                        <textarea name="contribution_note" rows="2" maxlength="1000" placeholder="အဓိကတာဝန်၊ expected contribution စသည်">{{ $profileData['contribution_note'] ?? '' }}</textarea>
                                    </label>
                                    <button class="pbr-os-btn secondary" type="submit">Update Roster</button>
                                </form>

                                @if(!$profile->user_id)
                                    <form method="POST" action="{{ route('workspaces.partner-roster.destroy', [$workspace, $profile]) }}" onsubmit="return confirm('ဒီ Planned Partner ကို roster ကနေဖယ်ရှားမလား?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pbr-roster-delete">Remove Planned Partner</button>
                                    </form>
                                @endif
                            </article>
                        @empty
                            <div class="pbr-os-empty-state">
                                <div class="pbr-os-empty-icon">◎</div>
                                <h2>Roster အလွတ်ဖြစ်နေပါတယ်</h2>
                                <p>Owner profile ကို sync လုပ်လိုက်တဲ့အတွက် ပုံမှန်အားဖြင့် ဒီနေရာမှာ Owner အနည်းဆုံးတစ်ယောက်ရှိရပါမယ်။</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="pbr-os-panel">
                    <div class="pbr-os-panel-head">
                        <div>
                            <span class="portal-kicker">Planning Mode</span>
                            <h2>Planned Partner ထည့်ရန်</h2>
                            <p>မ invite ရသေးတဲ့ co-founder / partner ကို planning tools အတွက်ကြိုထည့်နိုင်ပါတယ်။ နောက်ပိုင်း invitation နဲ့ connect လုပ်တဲ့အခါ duplicate identity မဖြစ်အောင် roster ကို review လုပ်ပါ။</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('workspaces.partner-roster.store', $workspace) }}" class="pbr-roster-create-form">
                        @csrf
                        <label>
                            <span>Partner အမည်</span>
                            <input type="text" name="display_name" maxlength="160" required placeholder="ဥပမာ Aung Aung">
                        </label>
                        <label>
                            <span>Role / Working Title</span>
                            <input type="text" name="role_title" maxlength="160" placeholder="ဥပမာ Sales & Growth Partner">
                        </label>
                        <label class="wide">
                            <span>Contribution / Responsibility Note</span>
                            <textarea name="contribution_note" rows="3" maxlength="1000" placeholder="ဘာတွေ contribute လုပ်မလဲ၊ ဘာတာဝန်ယူမလဲ"></textarea>
                        </label>
                        <div class="wide">
                            <button type="submit" class="pbr-os-btn primary">+ Add Planned Partner</button>
                        </div>
                    </form>
                </section>

                <div class="pbr-os-legal-note">
                    <strong>Partner Roster ≠ Legal Ownership Register</strong>
                    <p>ဒီ Roster က PBR planning/management tools အတွက် shared identity layer ဖြစ်ပါတယ်။ Legal shareholder/member register, company filing သို့မဟုတ် partnership agreement ကို အစားမထိုးပါ။</p>
                </div>
            </main>
        </div>
    </div>
</section>
@endsection
