@extends('layouts.student-portal')

@section('title', 'Workspace Invitation')

@section('content')

<section class="auth-section">

    <div class="auth-shell compact">

        <div class="auth-copy">

            <span class="portal-kicker">
                Partnership Invitation
            </span>

            <h1>
                Workspace သို့ ဖိတ်ကြားထားပါသည်
            </h1>

            <p>
                ဒီ Invitation က အောက်မှာပြထားတဲ့
                Workspace ကို Partner အဖြစ်
                အသုံးပြုခွင့်ပေးမှာဖြစ်ပါတယ်။
                သင့်မူလ Account Type ကို မပြောင်းပါဘူး။
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
                <strong>Your Role:</strong>
                Partner
            </div>

            @unless($isShareable)
                <div class="auth-note">
                    <strong>Invited Email:</strong>
                    {{ $invitation->invited_email }}
                </div>
            @endunless

            <div class="auth-note">
                <strong>Access:</strong><br>
                ဒီ Workspace ထဲက Partnership Tools,
                Partner Dynamics, Decisions,
                Comments, Approvals နဲ့ Documents
                တွေကို Workspace Permission အတိုင်း
                အသုံးပြုနိုင်ပါမယ်။
            </div>

            @if($isShareable)
                <div class="auth-note">
                    <strong>Secure Single-use Link</strong><br>
                    ဒီ Link ကို Account တစ်ခုက
                    Accept လုပ်ပြီးတာနဲ့
                    နောက်တစ်ကြိမ် အသုံးမပြုနိုင်တော့ပါ။
                </div>
            @endif


            @error('invitation')
                <div class="field-error invite-error">
                    {{ $message }}
                </div>
            @enderror


            @guest

                <div class="auth-note">
                    Workspace ကိုချိတ်ဆက်ဖို့
                    အရင် Login ဝင်ပါ။
                    Account မရှိသေးရင်
                    Free Account ဖန်တီးနိုင်ပါတယ်။
                </div>

                <a
                    class="portal-button"
                    href="{{
                        $isShareable
                            ? route('login')
                            : route(
                                'login',
                                ['email' => $invitation->invited_email]
                            )
                    }}"
                >
                    Log In to Continue
                </a>

                <a
                    class="portal-button secondary"
                    href="{{
                        $isShareable
                            ? route('register')
                            : route(
                                'register',
                                ['email' => $invitation->invited_email]
                            )
                    }}"
                >
                    Create Account
                </a>

            @else

                @if(
                    $isShareable
                    || strtolower(auth()->user()->email)
                        === strtolower($invitation->invited_email)
                )

                    <form
                        method="POST"
                        action="{{ route(
                            'workspace-invitations.accept',
                            ['token' => $token]
                        ) }}"
                    >
                        @csrf

                        <button
                            class="portal-button"
                            type="submit"
                        >
                            Connect This Workspace
                        </button>
                    </form>

                @else

                    <div class="auth-note warning-note">

                        လက်ရှိ Login ဝင်ထားတဲ့ Account:

                        <strong>
                            {{ auth()->user()->email }}
                        </strong>

                        <br><br>

                        ဒီ Invitation က
                        တခြား Email Address အတွက်ဖြစ်ပါတယ်။

                    </div>

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button
                            class="portal-button secondary"
                            type="submit"
                        >
                            Log Out and Use Invited Account
                        </button>
                    </form>

                @endif

            @endguest

        </div>

    </div>

</section>

@endsection
