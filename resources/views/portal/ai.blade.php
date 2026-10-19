@extends('portal.layout')
@section('title', 'PBR AI')
@section('body-class', 'portal-chat-route')
@section('content')
@php($workspaceAI = isset($workspace))
@php($aiUrl = $workspaceAI ? route('portal.workspaces.ai', $workspace) : route('portal.ai'))
<section class="chat-page {{ $workspaceAI ? 'workspace-chat' : '' }}" id="portal-ai" data-workspace="{{ $workspaceAI ? $workspace->name : '' }}" data-endpoint="{{ $workspaceAI ? route('portal.workspaces.ai.chat', $workspace) : route('portal.ai.chat') }}" data-conversation="{{ $selected?->id }}" data-stale="{{ ($stale ?? false) ? 'true' : 'false' }}">
    <header class="chat-toolbar">
        <span class="chat-brand-icon">@include('portal.icon', ['icon'=>'chat'])</span>
        <div class="chat-heading"><h1>PBR AI</h1>
            @if($workspaceAI)
            <details class="chat-context-menu"><summary>{{ $workspace->name }} @include('portal.icon', ['icon'=>'chevron'])</summary><nav aria-label="Workspace"><a href="{{ route('portal.workspaces.show', $workspace) }}">Overview</a><a href="{{ route('portal.workspaces.knowledge.index', $workspace) }}">Knowledge <span>{{ $readyCount }}</span></a><a href="{{ route('portal.workspaces.partners', $workspace) }}">Partners</a></nav></details>
            @else <span class="chat-personal-label">Partnership</span> @endif
        </div>
        <div class="chat-actions">
            @if($workspaceAI)<a class="chat-private-mark" href="{{ route('portal.workspaces.knowledge.index', $workspace) }}" aria-label="Owner-only Knowledge" title="Owner သီးသန့် · Knowledge {{ $readyCount }}">@include('portal.icon', ['icon'=>'lock'])</a>@endif
            <details class="portal-history"><summary class="icon-btn" aria-label="စကားဝိုင်းများ" title="History">@include('portal.icon', ['icon'=>'history'])</summary><div class="history-list">
                @forelse($conversations as $conversation)<a data-conversation="{{ $conversation->id }}" @if($selected?->id === $conversation->id) aria-current="page" @endif href="{{ $aiUrl.'?conversation='.$conversation->id }}">{{ $conversation->title }}</a>@empty<p class="small muted" data-empty-history>စကားဝိုင်း မရှိသေးပါ။</p>@endforelse
                @include('portal.pagination', ['paginator'=>$conversations])
            </div></details>
            <a class="icon-btn" href="{{ $aiUrl }}" aria-label="စကားဝိုင်းအသစ်" title="New chat">@include('portal.icon', ['icon'=>'plus'])</a>
            <form id="portal-delete-chat" method="post" action="{{ $selected ? ($workspaceAI ? route('portal.workspaces.ai.destroy', [$workspace, $selected]) : route('portal.ai.destroy', $selected)) : $aiUrl }}" data-delete-base="{{ $aiUrl }}" data-confirm="ဒီစကားဝိုင်းကို ဖျက်မလား?" @if(!$selected) hidden @endif>@csrf @method('delete')<button class="icon-btn" aria-label="စကားဝိုင်းဖျက်မယ်" title="Delete chat">@include('portal.icon', ['icon'=>'trash'])</button></form>
        </div>
    </header>
    @if($stale ?? false)<div class="chat-notice" role="status">Knowledge ပြောင်းထားပါတယ်။ <a href="{{ $aiUrl }}">စကားဝိုင်းအသစ် စမယ် →</a></div>@endif
    <div class="chat-scroll" id="chat-scroll"><div class="message-list" id="portal-messages">
        @forelse($messages as $message)
        <article class="message {{ $message->role }}">
            <span class="message-avatar" aria-hidden="true">@if($message->role === 'assistant')<img src="{{ asset('images/pbr-logo.png') }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@endif</span>
            <div class="message-content"><div class="message-body"><div class="portal-reply" @if($message->role === 'assistant') data-format-reply @endif>{{ $message->content }}</div>
                @if($workspaceAI)@include('portal.workspaces.knowledge.citations', ['links'=>$message->citation_links ?? []])@endif
                @if($message->metadata['incomplete'] ?? false)<small class="error">အဖြေ မပြီးဆုံးသေးပါ။ / Incomplete response.</small>@endif
            </div><div class="message-meta"><time>{{ $message->created_at->copy()->timezone(config('app.display_timezone'))->format('H:i') }}</time>@if($message->role === 'assistant')<button type="button" class="reply-copy" data-copy-reply aria-label="Copy answer" title="Copy">@include('portal.icon', ['icon'=>'copy'])</button>@endif</div></div>
        </article>
        @empty
        <div class="chat-welcome" id="portal-welcome">
            <div class="ai-emblem"><img src="{{ asset('images/pbr-logo.png') }}" alt=""></div>
            <h2 data-chat-welcome>ဘာဆွေးနွေးကြမလဲ?</h2>
            <div class="suggestion-grid">
                @foreach([
                    ['အမြတ်ခွဲဝေမှု', 'Profit sharing', 'အမြတ်ကို ဘယ်လို မျှမျှတတ ခွဲသင့်လဲ?', 'How should we split profits fairly?', 'coin'],
                    ['Partner တာဝန်', 'Partner roles', 'Partner တာဝန်တွေ ဘယ်လို ခွဲသင့်လဲ?', 'How should we divide partner responsibilities?', 'users'],
                    ['သဘောတူညီချက်', 'Agreements', 'Partnership သဘောတူညီချက်မှာ ဘာတွေ ပါသင့်လဲ?', 'What should a partnership agreement include?', 'checklist'],
                    ['Partner ထွက်ခွာမှု', 'Partner exit', 'Partner တစ်ယောက် ထွက်ချင်ရင် ဘာတွေ စစ်သင့်လဲ?', 'What should we check when a partner wants to leave?', 'logout'],
                ] as [$labelMy, $labelEn, $promptMy, $promptEn, $icon])
                <button type="button" class="suggestion" data-ai-prompt="{{ $promptMy }}" data-ai-prompt-my="{{ $promptMy }}" data-ai-prompt-en="{{ $promptEn }}">@include('portal.icon', ['icon'=>$icon])<span data-label-my="{{ $labelMy }}" data-label-en="{{ $labelEn }}">{{ $labelMy }}</span></button>
                @endforeach
            </div>
        </div>
        @endforelse
    </div></div>
    <div class="chat-compose"><div class="compose-wrap">
        @if($workspaceAI && $readyCount === 0)<a class="chat-add-knowledge" href="{{ route('portal.workspaces.knowledge.create', $workspace) }}">@include('portal.icon', ['icon'=>'plus']) Knowledge ထည့်မယ်</a>@endif
        <form id="portal-chat-form" class="composer"><label class="sr-only" for="portal-message">Message in Burmese or English</label><textarea id="portal-message" maxlength="5000" rows="1" required @disabled($stale ?? false) placeholder="မြန်မာလို သို့မဟုတ် English လို မေးပါ…">{{ $prefill ?? '' }}</textarea><button class="send-btn" id="portal-send" @disabled($stale ?? false) aria-label="Send message">@include('portal.icon', ['icon'=>'send'])</button></form>
        <div class="compose-caption"><label class="chat-language" for="portal-reply-language"><span class="sr-only">အဖြေဘာသာ / Reply language</span><select id="portal-reply-language" aria-label="အဖြေဘာသာ / Reply language"><option value="auto">Auto · မြန်မာ / EN</option><option value="my">မြန်မာ</option><option value="en">English</option></select></label><span class="chat-key-hint" aria-hidden="true">Enter ↵</span></div>
        <p id="portal-chat-status" class="sr-only" role="status" aria-live="polite"></p><p id="portal-chat-error" class="error" role="alert"></p>
    </div></div>
</section>
<template id="portal-assistant-avatar"><img src="{{ asset('images/pbr-logo.png') }}" alt=""></template>
<template id="portal-copy-icon">@include('portal.icon', ['icon'=>'copy'])</template>
@endsection
