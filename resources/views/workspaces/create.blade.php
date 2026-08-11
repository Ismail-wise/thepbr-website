@extends('layouts.student-portal')

@section('title', 'Add New Business')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Multi-Business</span>
            <h1>Business အသစ်ထည့်ပါ</h1>
            <p>Account တစ်ခုတည်းနဲ့ Business အများကြီးကို သီးခြား Workspace အဖြစ် စီမံနိုင်ပါတယ်။ Business တစ်ခုချင်းစီမှာ Partner, Tools, Assessment နဲ့ Data သီးခြားဖြစ်ပါမယ်။</p>
        </div>

        <div class="auth-card">
            <form method="POST" action="{{ route('workspaces.store') }}">
                @csrf
                <div class="field">
                    <label for="business_name">Business Name</label>
                    <input id="business_name" name="business_name" type="text" value="{{ old('business_name') }}" placeholder="Example: ABC Trading" required>
                    @error('business_name')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="field">
                    <label for="business_stage">Business အခြေအနေ</label>
                    <select id="business_stage" name="business_stage" required>
                        @foreach($businessStages as $value => $label)
                            <option value="{{ $value }}" @selected(old('business_stage', 'new') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="currency_code">Main Currency</label>
                    <select id="currency_code" name="currency_code" required>
                        @foreach($currencies as $value => $label)
                            <option value="{{ $value }}" @selected(old('currency_code', 'THB') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button class="portal-button" type="submit">Create Business Workspace</button>
                <a class="portal-button secondary" href="{{ route('workspaces.index') }}">Cancel</a>
            </form>
        </div>
    </div>
</section>
@endsection
