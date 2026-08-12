@extends('layouts.student-portal')

@section('title', 'My PBR Workspace')

@section('content')
<section class="workspace-section">
    <div class="portal-wrap">
        <div class="workspace-head">
            <div>
                <span class="portal-kicker">MY PBR WORKSPACE</span>
                <h1>မင်္ဂလာပါ၊ {{ $user->name }}</h1>
                <p>သင်တန်းအတွက် Learning Portal နဲ့ တကယ်အသုံးပြုမယ့် Business Operating System ကို Account တစ်ခုတည်းကနေ သုံးနိုင်ပါတယ်။</p>
            </div>
            <div class="status-pill">Student · Active</div>
        </div>

        <div class="workspace-grid">
            <article class="workspace-card primary-card">
                <span>CLASS BATCH</span>
                <h2>{{ $user->classSession?->title ?? 'သင်တန်းအုပ်စု မသတ်မှတ်ရသေးပါ' }}</h2>
                @if($user->classSession)
                    <p>{{ $user->classSession->location }} · {{ $user->classSession->time_note ?: 'အချိန်ဇယား ကြေညာပါမည်' }}</p>
                @endif
            </article>

            <article class="workspace-card">
                <span>LEARNING</span>
                <h2>သင်ယူရန် အခန်း {{ $chapterCount }} ခု</h2>
                <p><small>10 Learning Chapters</small><br>သင်တန်းထဲမှာ Partnership အယူအဆနဲ့ Framework တွေကို လေ့လာပြီး အောက်က Business Workspace ထဲမှာ ကိုယ့်လုပ်ငန်းအတွက် တကယ်အသုံးချနိုင်ပါတယ်။</p>
            </article>

            <article class="workspace-card">
                <span>PARTNER DYNAMICS</span>
                <h2>သင့် Partnership Operating Style ကို သိပါ</h2>
                <p>သင့် strengths၊ decision style နဲ့ Partnership ထဲမှာ သဘာဝကျကျ အားသာတဲ့ role တွေကို ရှာဖွေပါ။</p>
                <a class="pd-inline-link" href="{{ route('partner-dynamics.index') }}">
                    Assessment ဆက်လုပ်ရန် →
                </a>
            </article>

            <article class="workspace-card">
                <span>BUSINESS OPERATING SYSTEM</span>
                <h2>Business {{ $workspaces->count() }} ခု ချိတ်ဆက်ထားသည်</h2>
                @if($workspaces->isNotEmpty())
                    @php($firstWorkspace = $workspaces->first())
                    <p>Partner Rules၊ Finance၊ Ownership၊ Governance၊ Risk နဲ့ ဆုံးဖြတ်ချက်တွေကို ကိုယ့်လုပ်ငန်းအလိုက် တကယ်စီမံနိုင်ပါတယ်။</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.tools.index', $firstWorkspace) }}">
                        Business Operating System ဖွင့်ရန် →
                    </a>
                @else
                    <p>Partnership data နဲ့ operating rules တွေကို စတင်စီမံဖို့ Business Workspace တစ်ခုအရင်တည်ဆောက်ပါ။</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.create') }}">
                        Business Workspace တည်ဆောက်ရန် →
                    </a>
                @endif
            </article>
        </div>

        <div class="coming-panel">
            <div style="width:100%;">
                <span class="portal-kicker">YOUR BUSINESSES</span>

                @if($workspaces->isNotEmpty())
                    <h2>စီမံချင်တဲ့ Business ကို ရွေးပါ</h2>
                    <p>Business Workspace တစ်ခုချင်းစီမှာ Partner Data၊ Financial Data၊ Draft၊ Active Business Rules နဲ့ Operating Records တွေကို သီးသန့်သိမ်းထားပါတယ်။</p>

                    <div style="display:grid;gap:12px;margin-top:18px;">
                        @foreach($workspaces as $workspace)
                            @php($agreedCount = (int) ($agreedToolCounts[$workspace->id] ?? 0))
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid rgba(30,92,42,.14);border-radius:14px;background:#fff;flex-wrap:wrap;">
                                <div>
                                    <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                                    <div style="margin-top:4px;font-size:13px;opacity:.72;">
                                        {{ strtoupper($workspace->currency_code ?? 'THB') }} · အသုံးပြုနေသော Rule {{ $agreedCount }} ခု
                                    </div>
                                </div>
                                <a class="pd-inline-link" href="{{ route('workspaces.tools.index', $workspace) }}">
                                    Business System ဖွင့်ရန် →
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <h2>ပထမဆုံး Business Workspace ကို တည်ဆောက်ပါ</h2>
                    <p>Business Operating System က တကယ်တွက်ထားတဲ့ data၊ Partner Rules နဲ့ Operating Data ကို Business တစ်ခုချင်းစီအလိုက် သိမ်းထားပေးပါတယ်။</p>
                    <a class="pd-inline-link" href="{{ route('workspaces.create') }}">Business Workspace တည်ဆောက်ရန် →</a>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
