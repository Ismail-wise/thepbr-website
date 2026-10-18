@extends('portal.layout')
@section('title', 'Knowledge · '.$workspace->name)
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><span class="eyebrow">YOUR BUSINESS, IN CONTEXT</span><h1>Knowledge</h1><p>{{ $workspace->name }} · Owner သီးသန့်</p></div><a class="btn" href="{{ route('portal.workspaces.knowledge.create', $workspace) }}">@include('portal.icon', ['icon'=>'folder']) Knowledge ထည့်မယ်</a></div>
    <p class="muted pbr-knowledge-intro">လုပ်ငန်းအချက်အလက်၊ သဘောတူညီချက်၊ မှတ်တမ်းတွေကို သိမ်းထားပါ။ သင်စစ်ပြီး အတည်ပြုထားတဲ့ စာသားကိုပဲ ဒီ Workspace ရဲ့ PBR AI က သုံးပါတယ်။</p>
    <div class="pbr-knowledge-list">
    @forelse($sources as $source)
        <a class="surface pbr-knowledge-card" href="{{ route('portal.workspaces.knowledge.show', [$workspace, $source]) }}"><div class="pbr-knowledge-card-top"><span class="pbr-knowledge-kind">{{ $source->kind === 'text' ? 'TEXT NOTE' : strtoupper(pathinfo($source->file_path ?? '', PATHINFO_EXTENSION)) }}</span><span class="small muted">{{ $source->updated_at->format('d M Y') }}</span></div><h2>{{ $source->title }}</h2><div class="pbr-knowledge-tags">@include('portal.workspaces.knowledge.status')</div><p class="muted">{{ \Illuminate\Support\Str::limit($source->reviewed_text ?: 'ဖိုင်ကို ဖွင့်ပြီး စာသားဖတ်ယူမှု ပြန်စစ်ပါ။', 170) }}</p><span class="pbr-knowledge-open">ဖွင့်ကြည့်မယ် <span aria-hidden="true">↗</span></span></a>
    @empty
        <div class="surface pbr-knowledge-empty"><span class="eyebrow">START WITH WHAT YOU KNOW</span><h2>သင့်လုပ်ငန်းကို AI ပိုနားလည်စေမယ်</h2><p class="muted">လုပ်ငန်းအကျဉ်းချုပ်တစ်ခု၊ Partner သဘောတူညီချက်တစ်ခု ဒါမှမဟုတ် လုပ်ငန်းရဲ့ လက်ရှိအခြေအနေကို အရင်ထည့်ကြည့်ပါ။</p><a class="btn" href="{{ route('portal.workspaces.knowledge.create', $workspace) }}">ပထမဆုံး Knowledge ထည့်မယ်</a></div>
    @endforelse
    </div>
    @include('portal.pagination', ['paginator'=>$sources])
</section>
@endsection
