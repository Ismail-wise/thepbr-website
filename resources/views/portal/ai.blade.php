@extends('portal.layout')
@section('title', 'PBR AI')
@section('content')
<section class="chat-page" id="portal-ai" data-endpoint="{{ route('portal.ai.chat') }}" data-conversation="{{ $selected?->id }}">
    <div class="chat-toolbar"><div><h1>PBR AI</h1><p>Partnership အတွက် စဉ်းစားဖော်၊ ဆွေးနွေးဖော်</p></div><span class="spacer"></span><details class="portal-history"><summary class="btn secondary sm">စကားဝိုင်းများ</summary><div class="history-list">
        @forelse($conversations as $conversation)<a data-conversation="{{ $conversation->id }}" href="{{ route('portal.ai', ['conversation'=>$conversation->id]) }}">{{ $conversation->title }}</a>@empty<p class="small muted" data-empty-history>စကားဝိုင်း မရှိသေးပါ။</p>@endforelse
        @include('portal.pagination', ['paginator'=>$conversations])
    </div></details><a class="btn secondary sm" href="{{ route('portal.ai') }}">စကားဝိုင်းအသစ်</a></div>
    <div class="chat-scroll" id="chat-scroll"><div class="message-list" id="portal-messages">
        @forelse($messages as $message)<article class="message {{ $message->role }}"><span class="message-avatar">@include('portal.icon', ['icon'=>$message->role==='assistant'?'spark':'users'])</span><div class="message-body"><div class="portal-reply">{{ $message->content }}</div>@if($message->metadata['incomplete'] ?? false)<small class="error">အဖြေ မပြီးဆုံးသေးပါ။</small>@endif</div></article>
        @empty<div class="chat-welcome" id="portal-welcome"><div class="ai-emblem">@include('portal.icon', ['icon'=>'spark'])</div><div class="welcome-kicker">YOUR PARTNERSHIP THINKING PARTNER</div><h2>ဒီနေ့ ဘာအကြောင်း ဆွေးနွေးကြမလဲ?</h2><p class="lead">Partner ရွေးချယ်မှု၊ ရင်းနှီးငွေ၊ တာဝန်ခွဲဝေမှုနှင့် လုပ်ငန်းဆိုင်ရာ မေးခွန်းတွေကို မြန်မာလို မေးနိုင်ပါတယ်။</p><div class="suggestion-grid">
        @foreach(['Partner နဲ့ သဘောထားမတူရင် ဘယ်လို စဆွေးနွေးသင့်လဲ?','Partnership မစခင် ဘာတွေ ကြိုညှိထားသင့်လဲ?','ငွေထည့်သူနဲ့ လုပ်အားထည့်သူကြား ဘယ်လို စဉ်းစားညှိနှိုင်းသင့်လဲ?','Partner တစ်ယောက် ထွက်ချင်ရင် ဘာတွေ စစ်သင့်လဲ?'] as $prompt)<button type="button" class="suggestion" data-ai-prompt="{{ $prompt }}">{{ $prompt }}</button>@endforeach
        </div></div>@endforelse
    </div></div>
    <div class="chat-compose"><div class="compose-wrap"><form id="portal-chat-form" class="composer"><label class="sr-only" for="portal-message">PBR AI သို့ မေးခွန်း</label><textarea id="portal-message" maxlength="5000" rows="2" required placeholder="သိချင်တာကို မြန်မာလို ရေးမေးပါ…">{{ $prefill ?? '' }}</textarea><button class="send-btn" id="portal-send" aria-label="မေးခွန်း ပို့မယ်">@include('portal.icon', ['icon'=>'send'])</button></form><div class="compose-caption"><span id="portal-chat-status" role="status" aria-live="polite">သင့် Account နှင့် သီးခြားသိမ်းထားသော စကားဝိုင်း</span><span>Enter ပို့ရန် · Shift + Enter စာကြောင်းဆင်းရန်</span></div><p id="portal-chat-error" class="error" role="alert"></p></div></div>
</section>
@endsection
