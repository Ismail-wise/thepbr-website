@extends('layouts.student-portal')

@section('title', 'Business Settings')

@section('content')
@php
    $businessName = $workspace->business_name ?: $workspace->name;
@endphp

<div class="pbr2-page">
    <div class="pbr2-form-shell">
        <section class="pbr2-hero">
            <span class="pbr2-eyebrow">Business Settings</span>
            <h1>{{ $businessName }} ကို ပြင်ဆင်ရန်</h1>
            <p>Business Name, လက်ရှိအခြေအနေ နဲ့ Currency ကို ပြောင်းနိုင်ပါတယ်။ Stage ပြောင်းလိုက်ရင် အသုံးပြုနိုင်တဲ့ Tools တွေလည်း အလိုက်သင့် ပြောင်းသွားပါမယ်။</p>
            <div class="pbr2-actions">
                <a class="pbr2-btn secondary" href="{{ route('workspaces.show', $workspace) }}">Business ဆီပြန်သွားရန်</a>
            </div>
        </section>

        <form method="POST" action="{{ route('workspaces.update', $workspace) }}">
            @csrf
            @method('PUT')

            <section class="pbr2-form-card">
                <h2>Business အချက်အလက်</h2>
                <p>ပြောင်းလိုတဲ့အချက်တွေကိုသာ ပြင်ပြီး Save လုပ်ပါ။</p>

                <div class="pbr2-field">
                    <label for="business_name">Business Name</label>
                    <input id="business_name" name="business_name" type="text" value="{{ old('business_name', $businessName) }}" required>
                    @error('business_name')<small class="pbr2-error">{{ $message }}</small>@enderror
                </div>

                <div class="pbr2-choice-grid">
                    <div class="pbr2-choice">
                        <input id="stage-new" type="radio" name="business_stage" value="new" @checked(old('business_stage', $workspace->business_stage) === 'new') required>
                        <label for="stage-new">
                            <strong>Business အသစ် စီစဉ်နေသည်</strong>
                            <span>Planning a New Partnership</span>
                        </label>
                    </div>
                    <div class="pbr2-choice">
                        <input id="stage-existing" type="radio" name="business_stage" value="existing" @checked(old('business_stage', $workspace->business_stage) === 'existing') required>
                        <label for="stage-existing">
                            <strong>ရှိပြီးသား Business ကို စီမံနေသည်</strong>
                            <span>Managing an Existing Partnership</span>
                        </label>
                    </div>
                </div>

                <div class="pbr2-field">
                    <label for="currency_code">Main Currency</label>
                    <select id="currency_code" name="currency_code" required>
                        @foreach($currencies as $value => $label)
                            <option value="{{ $value }}" @selected(old('currency_code', $workspace->currency_code) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="pbr2-btn" type="submit">ပြောင်းလဲမှုများကို Save လုပ်ရန်</button>
            </section>
        </form>

        <section class="pbr2-danger">
            <h2>Danger Zone — Business ကို အပြီးဖျက်ရန်</h2>
            <p>ဒီလုပ်ဆောင်ချက်ကို ပြန်ပြင်၍မရပါ။ Business Workspace, Partner Access, Tool Sessions, Saved Outputs, Feasibility Results နဲ့ Valuation Results တွေကို အပြီးဖျက်ပါမယ်။</p>
            <p><strong>အတည်ပြုရန် Business Name ကို အတိအကျ ရိုက်ထည့်ပါ — {{ $businessName }}</strong></p>

            <form method="POST" action="{{ route('workspaces.destroy', $workspace) }}">
                @csrf
                @method('DELETE')
                <div class="pbr2-field">
                    <label for="confirmation_name">Business Name ကို ရိုက်ထည့်ပါ</label>
                    <input id="confirmation_name" name="confirmation_name" type="text" autocomplete="off" required>
                    @error('confirmation_name')<small class="pbr2-error">{{ $message }}</small>@enderror
                </div>
                <button class="pbr2-btn danger" type="submit">Business ကို အပြီးဖျက်ရန်</button>
            </form>
        </section>
    </div>
</div>
@endsection
