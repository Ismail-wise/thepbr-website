@extends('portal.layout')
@section('title', $item->title)
@section('content')
<section class="page"><div class="page-header"><div><span class="eyebrow">SAVED RESULT</span><h1>{{ $item->title }}</h1><p>{{ $item->created_at->format('d M Y, H:i') }}</p></div></div><div class="surface">
@if($item->kind==='capital')<dl class="account-details">@foreach(['total'=>'စုစုပေါင်းလိုအပ်ငွေ','working_capital'=>'လည်ပတ်ငွေ','contingency'=>'အရေးပေါ်ပြင်ဆင်ငွေ','available'=>'ရှိပြီး ရင်းနှီးငွေ','gap'=>'ထပ်လိုငွေ','surplus'=>'ပိုပြင်ဆင်ထားငွေ'] as $key=>$label)<dt>{{ $label }}</dt><dd><strong>{{ number_format($item->output[$key],2) }} {{ $item->output['currency'] }}</strong></dd>@endforeach</dl>
@elseif($item->kind==='shares')<div class="table-wrap"><table class="data-table"><thead><tr><th>Partner</th><th>ထည့်ဝင်တန်ဖိုး</th><th>ရှယ်ယာအရေအတွက်</th><th>အချိုး</th></tr></thead><tbody>@foreach($item->output['rows'] as $row)<tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['value'],2) }} {{ $item->output['currency'] }}</td><td>{{ number_format($row['shares']) }}</td><td>{{ $row['percent'] }}%</td></tr>@endforeach</tbody></table></div><p class="small muted">စမ်းတွက်ချက်ထားသော အချိုးဖြစ်ပြီး တရားဝင်ပိုင်ဆိုင်မှုကို မသတ်မှတ်ပါ။</p>
@else @foreach($item->output['items'] as $row)<div class="check-item"><h3>{{ $row['done']?'✓':'○' }} {{ $row['topic'] }}</h3><p class="portal-reply">{{ $row['note'] }}</p></div>@endforeach @endif
<div class="page-actions" style="margin-top:28px"><a class="btn" href="{{ route('portal.ai',['saved'=>$item->id]) }}">AI နဲ့ ဆွေးနွေးမယ်</a><a class="btn secondary" href="{{ route('portal.tool',$item->kind) }}">Input ပြန်သုံးမယ်</a><a class="btn ghost" href="{{ route('portal.library') }}">My Library</a></div>
<form method="post" action="{{ route('portal.saved.destroy',$item) }}" style="margin-top:25px" data-confirm="ဒီရလဒ်တစ်ခုကို ဖျက်မလား?">@csrf @method('DELETE')<button class="btn danger sm">ဒီရလဒ် ဖျက်မယ်</button></form>
</div></section>
@endsection
