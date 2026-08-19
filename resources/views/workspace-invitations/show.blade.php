@extends('layouts.student-portal')

@section('title', 'Workspace Invitation')

@section('content')
<section class="auth-section">
    <div class="auth-shell compact">
        <div class="auth-copy">
            <span class="portal-kicker">Secure Partnership Invitation</span>
            <h1>Workspace သို့ ဖိတ်ကြားထားပါသည်</h1>
            <p>
                ဒီ email-bound invitation ကို accept လုပ်ရင် သင့်မူလ Account Type မပြောင်းဘဲ
                ဒီ Workspace တစ်ခုထဲကို Partner Read-Only အဖြစ် ချိတ်ဆက်ပေးမှာပါ။
            </p>
        </div>

        <div class="auth-card">
            <div class="auth-note first-note">
                <strong>Workspace:</strong>
                {{ $invitation->workspace?->name ?? 'Private Workspace' }}
            </div>

            <div class="auth-note">
                <strong>Owner:</strong>
                {{ $invitation->workspace?->owner?->name ?? 'Unknown' }}
            </div>

            <div class="auth-note">
                <strong>Invited Email:</strong>
                {{ $invitation->invited_email }}
            </div>

            <div class="auth-note">
                <strong>Expires:</strong>
                {{ $invitation->invitation_expires_at?->format('M j, Y g:i A') }}
            </div>

            <div class="auth-note">
                <strong>Partner Access:</strong><br>
                Owner က approve လုပ်ထားတဲ့ Current Rules နဲ့ shared workspace information ကို
                read-only ကြည့်နိုင်ပါတယ်။ Tools ကို edit/approve လုပ်ခြင်း၊ private drafts၊
                Feasibility နဲ့ Valuation data ကို ကြည့်ခြင်း မပြုလုပ်နိုင်ပါ။
            </div>

            <div class="auth-note">
                <strong>Single-use Security</strong><br>
                ဒီ link က invited email account တစ်ခုတည်းအတွက်ဖြစ်ပြီး တစ်ကြိမ် accept လုပ်ပြီးတာနဲ့
                ပြန်အသုံးပြုလို့မရတော့ပါ။
            </div>

            @error('invitation')
                <div class="field-error invite-error">{{ $message }}</div>
            @enderror

            @guest
                <div class="auth-note">
                    Workspace ကိုချိတ်ဆက်ဖို့ invited email နဲ့ Login ဝင်ပါ။
                    Account မရှိသေးရင် အဲဒီ email နဲ့ Free Account ဖန်တီးနိုင်ပါတယ်။
                </div>

                <a
                    class="portal-button"
                    href="{{ route('login', ['email' => $invitation->invited_email]) }}"
                >
                    Log In to Continue
                </a>

                <a
                    class="portal-button secondary"
                    href="{{ route('register', ['email' => $invitation->invited_email]) }}"
                >
                    Create Account
                </a>
            @else
                @if(strtolower(auth()->user()->email) === strtolower($invitation->invited_email))
                    <form
                        method="POST"
                        action="{{ route('workspace-invitations.accept', ['token' => $token]) }}"
                    >
                        @csrf
                        <button class="portal-button" type="submit">
                            Accept Partner Read-Only Access
                        </button>
                    </form>
                @else
                    <div class="auth-note warning-note">
                        လက်ရှိ Login ဝင်ထားတဲ့ Account:
                        <strong>{{ auth()->user()->email }}</strong>
                        <br><br>
                        ဒီ Invitation က {{ $invitation->invited_email }} အတွက်ဖြစ်ပါတယ်။
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="portal-button secondary" type="submit">
                            Log Out and Use Invited Account
                        </button>
                    </form>
                @endif
            @endguest
        </div>
    </div>
</section>
@endsection
