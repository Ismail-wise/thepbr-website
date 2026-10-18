@extends('portal.layout')
@section('title', $source->title.' · Knowledge')
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><span class="eyebrow">REVIEW YOUR SOURCE</span><h1>{{ $source->title }}</h1><p>{{ $workspace->name }} · Owner သီးသန့်</p></div><a class="btn secondary" href="{{ route('portal.workspaces.knowledge.download', [$workspace, $source]) }}">မူရင်းကို Download လုပ်မယ်</a></div>
    <div class="pbr-knowledge-tags">@include('portal.workspaces.knowledge.status')</div>
    @if(request()->integer('revision') > 0 && request()->integer('revision') !== $source->revision)<div class="portal-feedback" role="status">ဒီမှတ်တမ်းကို Chat အဖြေရပြီးနောက် ပြင်ထားပါတယ်။ အောက်မှာ လက်ရှိ version ကို ပြထားပါတယ်။</div>@endif
    @if($source->extracted_partial)<div class="portal-feedback" role="status">ဖိုင်တစ်ခုလုံးကို ပြည့်စုံစွာ ဖတ်ယူမရခဲ့ပါ။ မူရင်းနဲ့ တိုက်စစ်ပြီး လိုတဲ့အကြောင်းအရာ ဖြည့်ပေးပါ။</div>@endif
    @if($source->processing_status === 'extraction_failed')<div class="portal-feedback" role="status">မူရင်းဖိုင်ကို သိမ်းထားပါတယ်။ စာသားဖတ်ယူမှု မပြီးသေးပါ။ ထပ်စမ်းနိုင်သလို အောက်မှာ ကိုယ်တိုင် ရေးထည့်နိုင်ပါတယ်။</div><form method="post" action="{{ route('portal.workspaces.knowledge.extract', [$workspace, $source]) }}" data-knowledge-submit>@csrf<input type="hidden" name="revision" value="{{ $source->revision }}"><button class="btn secondary" data-knowledge-button>စာသား ပြန်ဖတ်ယူမယ်</button><p data-knowledge-progress role="status" class="small muted"></p></form>@endif
    <form class="surface pbr-knowledge-form pbr-knowledge-review" method="post" action="{{ route('portal.workspaces.knowledge.update', [$workspace, $source]) }}" data-knowledge-submit>
        @csrf @method('put')<input type="hidden" name="revision" value="{{ $source->revision }}">
        <label for="review-title">ခေါင်းစဉ်</label><input id="review-title" name="title" maxlength="160" value="{{ old('title', $source->title) }}" required>
        <label for="review-business">ဒီမှတ်တမ်းရဲ့ အခြေအနေ</label><select id="review-business" name="business_status" required>@foreach(\App\Models\WorkspaceKnowledgeSource::BUSINESS_STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('business_status', $source->business_status)===$value)>{{ $label }}</option>@endforeach</select>
        <label for="review-content">AI သုံးမယ့် စာသားကို စစ်ပြီး ပြင်မယ်</label><textarea id="review-content" name="content" rows="18" maxlength="60000" required>{{ old('content', $source->reviewed_text) }}</textarea>
        <p class="small muted">နာမည်၊ နံပါတ်၊ ရက်စွဲနဲ့ စာလုံးတွေကို မူရင်းနဲ့ တိုက်စစ်ပါ။ Draft/Historical ကို အတည်ပြုထားရင်လည်း AI က လက်ရှိသဘောတူညီချက်အဖြစ် မယူဆသင့်ပါ။</p>
        <label class="pbr-knowledge-confirm"><input type="checkbox" name="reviewed" value="1"> <span>အပေါ်က စာသားကို စစ်ပြီးပါပြီ။ ဒီ Workspace ရဲ့ AI က အသုံးပြုနိုင်ပါတယ်။</span></label>
        <div class="page-actions"><button class="btn" name="action" value="approve" data-knowledge-button>AI သုံးရန် အတည်ပြုမယ်</button><button class="btn secondary" name="action" value="draft" data-knowledge-button>စစ်ဆေးဆဲအဖြစ် သိမ်းမယ်</button></div><p class="small muted" data-knowledge-progress role="status"></p>
    </form>
    <div class="pbr-knowledge-footer"><p class="small muted">Knowledge ဖယ်ပြီးနောက် Chat အဟောင်းထဲက အဖြေတွေ ကျန်နိုင်ပါတယ်။ စကားဝိုင်းကို သီးခြားဖျက်နိုင်ပါတယ်။</p><form method="post" action="{{ route('portal.workspaces.knowledge.destroy', [$workspace, $source]) }}" data-confirm="ဒီ Knowledge နှင့် မူရင်းဖိုင်ကို ဖယ်မလား?">@csrf @method('delete')<button class="btn secondary sm">Knowledge ဖယ်မယ်</button></form></div>
</section>
@endsection
