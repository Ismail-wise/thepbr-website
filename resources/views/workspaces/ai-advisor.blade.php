@extends('layouts.student-portal')

@section('title', 'PBR AI Advisor — '.$businessName)

@section('content')
<link rel="stylesheet" href="{{ asset('css/pbr-ai-advisor.css') }}?v={{ filemtime(public_path('css/pbr-ai-advisor.css')) }}">

<div class="pbr2-page">
    <section class="pbr2-hero">
        <div class="pbr2-hero-row">
            <div>
                <span class="pbr2-eyebrow">PBR AI Advisor</span>
                <h1>{{ $businessName }} အတွက် AI Business Advisor</h1>
                <p>
                    PBR ရဲ့ မူလ Partnership Knowledge Base, Training Materials, RAG Knowledge နဲ့ ဒီ Business ရဲ့
                    Feasibility, Valuation, Ownership, Partner Data နဲ့ Saved Tool Outputs တွေကို ချိတ်ဆက်ပြီး အကြံပြုပေးပါတယ်။
                </p>
            </div>
            <div class="pbr2-actions">
                <a class="pbr2-btn secondary" href="{{ route('workspaces.show', $workspace) }}">Business Control Center</a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="pbr2-panel" style="border-color:#b9d9bf;background:#f4faf4;">
            {{ session('success') }}
        </div>
    @endif

    <section
        id="pbr-ai-advisor"
        data-chat-url="{{ route('workspaces.ai-advisor.chat', $workspace) }}"
        data-csrf="{{ csrf_token() }}"
        data-conversation-id="{{ $selectedConversation?->id ?? '' }}"
    >
        <div class="pbrai-shell">
            <aside class="pbrai-sidebar">
                <div class="pbrai-side-head">
                    <strong>AI စကားဝိုင်းများ</strong>
                    <a class="pbrai-new" href="{{ route('workspaces.ai-advisor.index', ['workspace' => $workspace, 'new' => 1]) }}" title="စကားဝိုင်းအသစ်">+</a>
                </div>

                <div class="pbrai-conversations">
                    @forelse($conversations as $conversation)
                        <div class="pbrai-conversation {{ $selectedConversation?->id === $conversation->id ? 'active' : '' }}">
                            <a href="{{ route('workspaces.ai-advisor.index', ['workspace' => $workspace, 'conversation' => $conversation->id]) }}">
                                <span class="pbrai-conversation-title">{{ $conversation->title ?: 'AI Conversation' }}</span>
                                <span class="pbrai-conversation-time">{{ $conversation->updated_at?->diffForHumans() }}</span>
                            </a>
                            <form
                                class="pbrai-delete"
                                data-pbrai-delete-form
                                method="POST"
                                action="{{ route('workspaces.ai-advisor.conversations.destroy', [$workspace, $conversation]) }}"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="ဖျက်ရန်">×</button>
                            </form>
                        </div>
                    @empty
                        <div style="font-size:11px;line-height:1.7;color:rgba(255,255,255,.48);padding:8px 3px;">
                            စကားဝိုင်းမရှိသေးပါ။ မေးခွန်းတစ်ခုစမေးလိုက်တာနဲ့ History ကို အလိုအလျောက်သိမ်းပေးပါမယ်။
                        </div>
                    @endforelse
                </div>

                <div class="pbrai-side-note">
                    စကားဝိုင်းတိုင်းကို သင့် Account + ဒီ Business Workspace နဲ့ သီးခြားသိမ်းထားပါတယ်။ တခြား User ရဲ့ private AI History ကို မမြင်နိုင်ပါ။
                </div>
            </aside>

            <main class="pbrai-main">
                <header class="pbrai-top">
                    <div class="pbrai-top-row">
                        <div class="pbrai-title">
                            <div class="pbrai-orb">AI</div>
                            <div>
                                <h2>PBR AI Advisor</h2>
                                <p>{{ $businessName }} • {{ $workspace->currency_code ?? 'Currency မသတ်မှတ်ရသေး' }}</p>
                            </div>
                        </div>
                        <span class="pbrai-live">AI Service</span>
                    </div>

                    <div class="pbrai-sourcebar">
                        <span class="pbrai-source">PBR Partnership Knowledge</span>
                        <span class="pbrai-source">Training PDFs + RAG</span>
                        <span class="pbrai-source">ဒီ Business ရဲ့ Live Data</span>
                        <span class="pbrai-source">Feasibility + Valuation + Tools</span>
                        <span class="pbrai-source">လိုအပ်ရင် Live Search</span>
                        @if($isManager)
                            <span class="pbrai-source manager">Owner/Admin Context</span>
                        @else
                            <span class="pbrai-source">Partner-safe Context</span>
                        @endif
                    </div>
                </header>

                <div id="pbrai-error" class="pbrai-error"></div>
                <div id="pbrai-status" class="pbrai-status"></div>

                <div id="pbrai-messages" class="pbrai-messages">
                    @if($selectedConversation && $selectedConversation->messages->isNotEmpty())
                        @foreach($selectedConversation->messages as $message)
                            <div class="pbrai-message {{ $message->role === 'user' ? 'user' : 'assistant' }}">
                                <div class="pbrai-bubble">
                                    <span class="pbrai-message-label">{{ $message->role === 'user' ? 'သင်' : 'PBR AI Advisor' }}</span>
                                    <span class="pbrai-message-body">{{ $message->content }}</span>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div id="pbrai-empty" class="pbrai-empty">
                            <div class="pbrai-empty-icon">✦</div>
                            <h3>ဒီ Business အကြောင်း ဘာမဆိုမေးနိုင်ပါတယ်</h3>
                            <p>
                                မူလ PBR Partnership Knowledge နဲ့ ဒီ Workspace ထဲမှာရှိပြီးသား Business Data နှစ်ခုလုံးကိုသုံးပြီး ဖြေပေးမှာပါ။
                                Data မရှိသေးတဲ့အချက်ဆိုရင်လည်း မရှိသေးကြောင်း ရှင်းရှင်းပြောပြီး ဘာဖြည့်သင့်လဲဆိုတာ ဆက်ညွှန်ပေးပါမယ်။
                            </p>
                            <div class="pbrai-starters">
                                <button class="pbrai-starter" type="button" data-pbrai-prompt="ဒီ Business ရဲ့ လက်ရှိအခြေအနေကို ခြုံငုံသုံးသပ်ပြီး အရေးကြီးဆုံးလုပ်စရာ 3 ခု ပြောပြပါ။">ဒီ Business ရဲ့ လက်ရှိအခြေအနေကို ခြုံငုံသုံးသပ်ပေးပါ</button>
                                <button class="pbrai-starter" type="button" data-pbrai-prompt="Feasibility Result နဲ့ လက်ရှိ Business Data အရ ဘာတွေကို အရင်ပြင်သင့်လဲ?">Feasibility Result အရ ဘာအရင်ပြင်ရမလဲ?</button>
                                <button class="pbrai-starter" type="button" data-pbrai-prompt="Valuation နဲ့ Ownership Data အရ Business Value တိုးဖို့ လက်တွေ့လုပ်ဆောင်ရမယ့်အချက်တွေ ပြောပြပါ။">Business Value တိုးဖို့ ဘာလုပ်သင့်လဲ?</button>
                                <button class="pbrai-starter" type="button" data-pbrai-prompt="Partner တွေနဲ့ Capital, Ownership, Role နဲ့ Decision Making ကို လက်ရှိ Data အရ ဘယ်လိုစနစ်တကျပြင်သင့်လဲ?">Partner Structure ကို ဘယ်လိုပိုကောင်းအောင်လုပ်မလဲ?</button>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="pbrai-composer-wrap">
                    <form id="pbrai-form" class="pbrai-composer">
                        <textarea
                            id="pbrai-input"
                            maxlength="5000"
                            rows="1"
                            placeholder="မေးချင်တာကို ဒီမှာရေးပါ… ဥပမာ — Partner ကို 25% ပေးထားတာ သင့်တော်လား?"
                            required
                        ></textarea>
                        <button id="pbrai-send" class="pbrai-send" type="submit" title="ပို့ရန်">↑</button>
                    </form>
                    <div class="pbrai-footnote">
                        AI အဖြေကို Business Planning အတွက် အသုံးပြုပါ။ Legal, Tax, Investment သို့မဟုတ် အရေးကြီးတဲ့ Financial Decision တွေမှာ သက်ဆိုင်ရာ Professional နဲ့ ထပ်စစ်ပါ။
                    </div>
                </div>
            </main>
        </div>
    </section>
</div>

<script src="{{ asset('js/pbr-ai-advisor.js') }}?v={{ filemtime(public_path('js/pbr-ai-advisor.js')) }}" defer></script>
@endsection
