@extends('portal.layout')
@section('title', ['capital'=>'Capital Calculator','shares'=>'Share Simulator','checklist'=>'Partnership Checklist'][$tool])
@section('content')
@php($input = $previous?->input ?? [])
<section class="page">
    <div class="page-header"><div><span class="eyebrow">PARTNERSHIP TOOLS</span><h1>{{ ['capital'=>'Capital Calculator','shares'=>'Share Simulator','checklist'=>'Partnership Checklist'][$tool] }}</h1><p>ကိုယ့်အခြေအနေကို ဖြည့်ပြီး ရလဒ်သိမ်းနိုင်ပါတယ်။</p></div></div>
    <form method="post" action="{{ route('portal.tool.save', $tool) }}" class="surface portal-tool-form">@csrf
    @if($tool==='capital')
        <div class="form-grid">
        @foreach(['pre'=>['မဖွင့်မီကုန်ကျစရိတ်',0],'assets'=>['စတင်ပစ္စည်းနှင့် ကုန်လက်ကျန်',0],'monthly'=>['တစ်လ လည်ပတ်ငွေ',0],'months'=>['ပြင်ဆင်မည့် လအရေအတွက်',3],'reserve'=>['အရေးပေါ်ပြင်ဆင်ငွေ (%)',10],'available'=>['ရှိပြီး ရင်းနှီးငွေ',0]] as $key=>[$label,$default])
        <div class="field"><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="{{ $key==='months'?'1':'0.01' }}" max="{{ $key==='months'?'120':($key==='reserve'?'100':'1000000000') }}" value="{{ old($key,$input[$key]??$default) }}" required></div>
        @endforeach
        </div>
    @elseif($tool==='shares')
        <div class="field"><label for="unit">ရှယ်ယာတစ်စုတန်ဖိုး</label><input id="unit" name="unit" type="number" min="0.01" step="0.01" max="1000000000" value="{{ old('unit',$input['unit']??100) }}" required></div>
        <p class="small muted">အတည်ပြုထားသော ထည့်ဝင်တန်ဖိုးများကို နှိုင်းယှဉ်စမ်းတွက်ရန် ဖြစ်ပါတယ်။ တရားဝင်ရှယ်ယာမှတ်တမ်းကို မပြောင်းပါ။</p>
        <div id="portal-partners">
        @foreach(old('partners',$input['partners']??[['name'=>'','value'=>0],['name'=>'','value'=>0]]) as $index=>$partner)
        <div class="partner-input" data-partner-row><label class="sr-only" for="partner-name-{{ $index }}">Partner နာမည်</label><input id="partner-name-{{ $index }}" name="partners[{{ $index }}][name]" value="{{ $partner['name'] }}" maxlength="100" placeholder="Partner နာမည်" required><label class="sr-only" for="partner-value-{{ $index }}">ထည့်ဝင်တန်ဖိုး</label><input id="partner-value-{{ $index }}" name="partners[{{ $index }}][value]" type="number" min="0" max="1000000000" step="0.01" value="{{ $partner['value'] }}" required><button class="icon-btn" type="button" data-remove-partner aria-label="Partner row ဖယ်ရန်">×</button></div>
        @endforeach
        </div><button class="btn secondary sm" type="button" id="portal-add-partner">Partner ထပ်ထည့်မယ်</button>
    @else
        @foreach($topics as $index=>$topic)
        <div class="check-item"><label><input type="hidden" name="checks[{{ $index }}][done]" value="0"><input type="checkbox" name="checks[{{ $index }}][done]" value="1" @checked(old('checks.'.$index.'.done',$input['checks'][$index]['done']??false))> {{ $topic }}</label><label class="sr-only" for="note-{{ $index }}">{{ $topic }} မှတ်စု</label><textarea id="note-{{ $index }}" name="checks[{{ $index }}][note]" rows="2" maxlength="2000" placeholder="ဆွေးနွေးထားသည်နှင့် ဆက်လုပ်ရန် မှတ်စု">{{ old('checks.'.$index.'.note',$input['checks'][$index]['note']??'') }}</textarea></div>
        @endforeach
    @endif
    @if($tool!=='checklist')<div class="field" style="margin-top:20px"><label for="currency">ငွေကြေးအမျိုးအစား</label><select id="currency" name="currency">@foreach(['THB','MMK','USD'] as $currency)<option @selected(old('currency',$input['currency']??'THB')===$currency)>{{ $currency }}</option>@endforeach</select></div>@endif
    <button class="btn" type="submit" style="margin-top:24px">{{ $tool==='checklist'?'မှတ်စု သိမ်းမယ်':'တွက်ချက်ပြီး ရလဒ်သိမ်းမယ်' }}</button>
    </form>
</section>
@endsection
