@extends('layouts.student-portal')

@section('title', 'Business အသစ်ထည့်ရန်')

@section('content')
<div class="pbr2-page">
    <div class="pbr2-form-shell">
        <section class="pbr2-hero">
            <span class="pbr2-eyebrow">Add New Business</span>
            <h1>Business အသစ်ကို စတင်သတ်မှတ်ပါ</h1>
            <p>အောက်က အချက် ၃ ခုပဲရွေးပါ။ Business တစ်ခုချင်းစီမှာ Partner, Tools, Assessment နဲ့ Data တွေကို သီးခြားစီ သိမ်းထားပါမယ်။</p>
        </section>

        <form method="POST" action="{{ route('workspaces.store') }}">
            @csrf

            <section class="pbr2-form-card">
                <h2>၁။ Business အမည်</h2>
                <p>အသုံးပြုသူတွေ အလွယ်တကူခွဲသိနိုင်မယ့် နာမည်ကိုထည့်ပါ။</p>

                <div class="pbr2-field">
                    <label for="business_name">Business Name</label>
                    <input id="business_name" name="business_name" type="text" value="{{ old('business_name') }}" placeholder="ဥပမာ - ABC Trading" required>
                    @error('business_name')<small class="pbr2-error">{{ $message }}</small>@enderror
                </div>
            </section>

            <section class="pbr2-form-card">
                <h2>၂။ လက်ရှိ Business အခြေအနေ</h2>
                <p>သင့် Business က အသစ်စတင်ဖို့ စီစဉ်နေသလား၊ ရှိပြီးသား Business ကို စီမံနေသလား ရွေးပါ။</p>

                <div class="pbr2-choice-grid">
                    <div class="pbr2-choice">
                        <input id="stage-new" type="radio" name="business_stage" value="new" @checked(old('business_stage', 'new') === 'new') required>
                        <label for="stage-new">
                            <strong>Business အသစ် စတင်ရန်</strong>
                            <span>Planning a New Partnership — စတင်မယ့် Business အတွက် Planning Tools တွေကို အသုံးပြုမယ်။</span>
                        </label>
                    </div>

                    <div class="pbr2-choice">
                        <input id="stage-existing" type="radio" name="business_stage" value="existing" @checked(old('business_stage') === 'existing') required>
                        <label for="stage-existing">
                            <strong>ရှိပြီးသား Business ကို စီမံရန်</strong>
                            <span>Managing an Existing Partnership — လက်ရှိ Business Data, Valuation နဲ့ Management Tools တွေကို အသုံးပြုမယ်။</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="pbr2-form-card">
                <h2>၃။ အဓိက Currency</h2>
                <p>တွက်ချက်မှုတွေအများစုမှာ သုံးမယ့် Currency ကိုရွေးပါ။ နောက်မှ Business Settings ထဲမှာ ပြန်ပြောင်းနိုင်ပါတယ်။</p>

                <div class="pbr2-field">
                    <label for="currency_code">Main Currency</label>
                    <select id="currency_code" name="currency_code" required>
                        @foreach($currencies as $value => $label)
                            <option value="{{ $value }}" @selected(old('currency_code', 'THB') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            <div class="pbr2-actions">
                <button class="pbr2-btn" type="submit">Business ကို ဖန်တီးရန်</button>
                <a class="pbr2-btn secondary" href="{{ route('workspaces.index') }}">မလုပ်တော့ပါ</a>
            </div>
        </form>
    </div>
</div>
@endsection
