@extends('portal.layout')
@section('title', 'Business Plan · ပြန်စစ်ရန်')
@include('portal.workspaces.business-plan.assets')
@section('content')
<section class="page pbr-plan-page">
    @include('portal.workspaces.business-plan.header')
    <div class="surface pbr-plan-notice" role="alert"><p>{{ ($busy ?? false) ? 'ဒီ Workspace မှာ လုပ်ဆောင်နေဆဲ ရှိပါတယ်။ ခဏနေရင် ပြန်စမ်းပေးပါ။' : 'အခြားစာမျက်နှာကနေ ပြင်ထားတာ ရှိပါတယ်။ လက်ရှိ Draft ကို ပြန်စစ်ပေးပါ။' }}</p>@if($submitted)<p>အခုရေးပို့ထားတဲ့စာကို အောက်မှာ ကူးယူနိုင်ပါတယ်။</p>@endif<div class="pbr-plan-actions"><a class="btn" href="{{ route('portal.workspaces.business-plan.edit', $workspace) }}" target="_blank" rel="noopener noreferrer">လက်ရှိ Plan ဖွင့်မယ်</a></div></div>
    @if($submitted)<h2>အခုရေးပို့ထားသောစာ</h2>@include('portal.workspaces.business-plan.summary', ['values'=>$submitted])@endif
</section>
@endsection
