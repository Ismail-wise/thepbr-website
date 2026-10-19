@extends('portal.layout')
@section('title', $source->title.' · Knowledge')
@section('content')
<section class="page">
    @include('portal.workspaces.tabs')
    <div class="page-header"><div><h1>{{ $source->title }}</h1><p>{{ $workspace->name }} · Owner သီးသန့်</p></div><a class="btn secondary" href="{{ route('portal.workspaces.knowledge.download', [$workspace, $source]) }}">@include('portal.icon', ['icon'=>'download']) မူရင်းဖိုင်</a></div>
    <div class="pbr-knowledge-tags">@include('portal.workspaces.knowledge.status')</div>
    @if(request()->integer('revision') > 0 && request()->integer('revision') !== $source->revision)<div class="portal-feedback" role="status">ဒီမှတ်တမ်းကို ပြင်ထားပါတယ်။ လက်ရှိ version ပါ။</div>@endif
    @if($source->extracted_partial)<div class="portal-feedback" role="status">စာသား မပြည့်စုံနိုင်ပါ။ မူရင်းနဲ့ တိုက်စစ်ပေးပါ။</div>@endif
    @if($source->processing_status === 'extraction_failed')<div class="portal-feedback" role="status">စာသားဖတ်ယူမှု မပြီးသေးပါ။ ထပ်စမ်းပါ သို့မဟုတ် ကိုယ်တိုင် ရေးထည့်ပါ။</div><form method="post" action="{{ route('portal.workspaces.knowledge.extract', [$workspace, $source]) }}" data-knowledge-submit>@csrf<input type="hidden" name="revision" value="{{ $source->revision }}"><button class="btn secondary" data-knowledge-button>စာသား ပြန်ဖတ်ယူမယ်</button><p data-knowledge-progress role="status" class="small muted"></p></form>@endif
    <form class="surface pbr-knowledge-form pbr-knowledge-review" method="post" action="{{ route('portal.workspaces.knowledge.update', [$workspace, $source]) }}" data-knowledge-submit>
        @csrf @method('put')<input type="hidden" name="revision" value="{{ $source->revision }}">
        <label for="review-title">ခေါင်းစဉ်</label><input id="review-title" name="title" maxlength="160" value="{{ old('title', $source->title) }}" required>
        <label for="review-business">အခြေအနေ</label><select id="review-business" name="business_status" required>@foreach(\App\Models\WorkspaceKnowledgeSource::BUSINESS_STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('business_status', $source->business_status)===$value)>{{ $label }}</option>@endforeach</select>
        <label for="review-content">စာသား ပြန်စစ်မယ်</label><textarea id="review-content" name="content" rows="12" maxlength="60000" required>{{ old('content', $source->reviewed_text) }}</textarea>
        <details class="quiet-details"><summary>စစ်ဆေးရန်</summary><p>နာမည်၊ နံပါတ်နဲ့ ရက်စွဲတွေကို မူရင်းနဲ့ တိုက်စစ်ပါ။ Draft/Historical က လက်ရှိအတည်ပြုချက် မဟုတ်ပါ။</p></details>
        <label class="pbr-knowledge-confirm"><input type="checkbox" name="reviewed" value="1"> <span>စစ်ပြီးပါပြီ။ ဒီ Workspace ရဲ့ AI မှာ သုံးနိုင်ပါတယ်။</span></label>
        <div class="page-actions"><button class="btn" name="action" value="approve" data-knowledge-button>AI သုံးရန် အတည်ပြုမယ်</button><button class="btn secondary" name="action" value="draft" data-knowledge-button>အကြမ်း သိမ်းမယ်</button></div><p class="small muted" data-knowledge-progress role="status"></p>
    </form>
    <div class="pbr-knowledge-footer"><details class="quiet-details"><summary>ဖျက်ခြင်းအကြောင်း</summary><p>Knowledge ဖယ်ပြီးနောက် Chat အဟောင်းထဲက အဖြေတွေ ကျန်နိုင်ပါတယ်။ စကားဝိုင်းကို သီးခြားဖျက်နိုင်ပါတယ်။</p></details><form method="post" action="{{ route('portal.workspaces.knowledge.destroy', [$workspace, $source]) }}" data-confirm="ဒီ Knowledge နှင့် မူရင်းဖိုင်ကို ဖယ်မလား?">@csrf @method('delete')<button class="btn secondary sm">Knowledge ဖယ်မယ်</button></form></div>
</section>
@endsection
