@extends('portal.layout')
@section('title', 'Knowledge ထည့်မယ် · '.$workspace->name)
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><span class="eyebrow">BUILD YOUR PRIVATE KNOWLEDGE</span><h1>Knowledge ထည့်မယ်</h1><p>{{ $workspace->name }} · Owner သီးသန့်</p></div></div>
    <div class="pbr-knowledge-create">
    <form class="surface pbr-knowledge-form" method="post" enctype="multipart/form-data" action="{{ route('portal.workspaces.knowledge.store', $workspace) }}" data-knowledge-submit>
        @csrf
        <label for="knowledge-title">ခေါင်းစဉ်</label><input id="knowledge-title" name="title" maxlength="160" value="{{ old('title') }}" placeholder="ဥပမာ — Partner တာဝန်ခွဲဝေမှု" required>
        <label for="knowledge-business">ဒီမှတ်တမ်းရဲ့ အခြေအနေ</label><select id="knowledge-business" name="business_status" required><option value="">ရွေးပေးပါ</option>@foreach(\App\Models\WorkspaceKnowledgeSource::BUSINESS_STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('business_status')===$value)>{{ $label }}</option>@endforeach</select>
        <label for="knowledge-content">စာသား ရေးထည့်မယ်</label><textarea id="knowledge-content" name="content" rows="10" maxlength="60000" placeholder="လုပ်ငန်းအကြောင်း၊ ဆုံးဖြတ်ချက်နဲ့ လက်ရှိအချက်အလက်တွေ ရေးနိုင်ပါတယ်။">{{ old('content') }}</textarea>
        <div class="pbr-knowledge-or"><span>သို့မဟုတ်</span></div>
        <label for="knowledge-file">Photo / PDF / Text File တင်မယ်</label><input id="knowledge-file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,application/pdf,image/jpeg,image/png,image/webp,text/plain">
        <p class="small muted">စာသားနဲ့ ဖိုင်ထဲက တစ်မျိုးကို ရွေးထည့်ပါ။ ဖိုင်တစ်ခုချင်း အများဆုံး {{ number_format(config('pbr_knowledge.upload_kb')/1024, 0) }} MB၊ server upload limit အတွင်း ဖြစ်ရပါမယ်။</p>
        <div class="page-actions"><button class="btn" data-knowledge-button>သိမ်းပြီး စစ်ဆေးမယ်</button><a class="btn secondary" href="{{ route('portal.workspaces.knowledge.index', $workspace) }}">နောက်သို့</a></div><p class="small muted" data-knowledge-progress role="status"></p>
    </form>
    <aside class="surface pbr-knowledge-guide"><span class="eyebrow">THREE SIMPLE STEPS</span><ol><li><strong>ထည့်ပါ</strong><p>စာသားရေးပါ၊ သို့မဟုတ် ဖိုင်တင်ပါ။</p></li><li><strong>စစ်ပါ</strong><p>ဖတ်ယူထားတဲ့ စာသား၊ နံပါတ်နဲ့ နာမည်တွေ မှန်အောင် ပြင်ပါ။</p></li><li><strong>အတည်ပြုပါ</strong><p>ပြီးမှ ဒီ Workspace ရဲ့ PBR AI မှာ သုံးနိုင်ပါမယ်။</p></li></ol><p class="small muted">စာသားဖတ်ယူခြင်း၊ ရှာဖွေခြင်းနှင့် အဖြေပေးခြင်းအတွက် ချိတ်ဆက်ထားသော AI provider က သင့်အကြောင်းအရာကို စီမံပေးမည်။ Portal အတွင်း Owner ကသာ ကြည့်နိုင်ပါတယ်။</p></aside>
    </div>
</section>
@endsection
