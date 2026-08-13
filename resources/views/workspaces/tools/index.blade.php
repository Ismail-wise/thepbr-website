@extends('layouts.student-portal')

@section('title', 'PBR Business Operating System')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $metrics = $businessState['metrics'];
    $systems = $businessState['systems'];
    $actions = $businessState['action_items'];
    $capital = $businessState['capital'];
    $stageMm = $workspace->business_stage === 'new'
        ? 'Partnership Business အသစ် စီစဉ်နေသည်'
        : 'ရှိပြီးသား Partnership Business ကို စီမံနေသည်';
@endphp

<section class="pbr-business-page">
    <div class="portal-wrap pbr-business-wrap">
        <nav class="pbr-os-breadcrumb pbr-business-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('workspaces.show', $workspace) }}">{{ $workspace->business_name ?: $workspace->name }}</a>
            <span>›</span>
            <span>Business Operating System</span>
        </nav>

        <header class="pbr-business-hero">
            <div class="pbr-business-hero-copy">
                <span class="pbr-business-eyebrow">PBR BUSINESS OPERATING SYSTEM</span>
                <h1>{{ $workspace->business_name ?: $workspace->name }}</h1>
                <p class="pbr-business-hero-lead">
                    ဒီနေရာက Calculator Library မဟုတ်ပါဘူး။ Capital ကနေ Conflict Resolution အထိ
                    <strong>actual company data၊ Working Changes၊ approved Rules နဲ့ Operating Records</strong>
                    ကို ချိတ်ဆက်ပြီး Partnership ကို ဆက်တိုက်စီမံဖို့ အသုံးပြုတဲ့ System ဖြစ်ပါတယ်။
                </p>
                <div class="pbr-business-tags">
                    <span>{{ $stageMm }}</span>
                    <span>{{ $currency }}</span>
                    <span>Partner {{ $metrics['partner_count'] }} ဦး</span>
                    @unless($businessState['can_manage'])
                        <span>Partner Read-Only</span>
                    @endunless
                </div>
            </div>

            <div class="pbr-business-hero-actions">
                <a href="{{ route('workspaces.partner-roster.index', $workspace) }}" class="pbr-business-btn secondary">Partner များ</a>
                <a href="{{ route('workspaces.ai-advisor.index', $workspace) }}" class="pbr-business-btn">PBR AI ကို မေးရန် ✦</a>
            </div>
        </header>

        @unless($businessState['can_manage'])
            <div class="pbr-os-readonly-banner">
                <div>
                    <strong>Partner Read-Only View</strong>
                    <p>ဒီ View မှာ Owner/Admin က အတည်ပြုထားတဲ့ Active Business Rules နဲ့ shared operating data ကိုသာ ပြထားပါတယ်။ Working Draft နဲ့ private scenario inputs မပါပါဘူး။</p>
                </div>
                <span>Permission Safe</span>
            </div>
        @endunless

        <section class="pbr-business-metrics" aria-label="Operating overview">
            <article class="pbr-business-metric">
                <span class="pbr-mm-label">လိုအပ်သော မတည်ငွေ</span>
                <small class="pbr-en-label">Capital Required</small>
                <strong>{{ $currency }} {{ number_format((float) $metrics['capital_required'], 2) }}</strong>
                <div class="pbr-metric-foot"><span>လက်ရှိ Capital Plan အပေါ်အခြေခံသည်</span></div>
            </article>

            <article class="pbr-business-metric {{ $metrics['funding_gap'] > 0 ? 'attention' : 'healthy' }}">
                <span class="pbr-mm-label">လိုအပ်နေသေးသော ရင်းနှီးငွေ</span>
                <small class="pbr-en-label">Funding Gap</small>
                <strong>{{ $currency }} {{ number_format((float) $metrics['funding_gap'], 2) }}</strong>
                <div class="pbr-metric-foot">
                    <b class="{{ $metrics['funding_gap'] > 0 ? 'warning' : 'active' }}">
                        {{ $metrics['funding_gap'] > 0 ? 'Action လိုသည်' : 'Funding လုံလောက်' }}
                    </b>
                </div>
            </article>

            <article class="pbr-business-metric">
                <span class="pbr-mm-label">အသုံးပြုနေသော Business Rules</span>
                <small class="pbr-en-label">Active Rules</small>
                <strong>{{ $metrics['active_rule_count'] }}</strong>
                <div class="pbr-metric-foot"><span>အတည်ပြုပြီး လက်ရှိအသုံးပြုနေသော Rules</span></div>
            </article>

            <article class="pbr-business-metric {{ $metrics['working_change_count'] > 0 ? 'draft' : '' }}">
                <span class="pbr-mm-label">Review လုပ်ရန် ပြောင်းလဲမှု</span>
                <small class="pbr-en-label">Working Changes</small>
                <strong>{{ $metrics['working_change_count'] }}</strong>
                <div class="pbr-metric-foot"><span>Active Rule မပြောင်းခင် Review လိုသော Draft</span></div>
            </article>
        </section>

        <section class="pbr-business-attention">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">ACTION CENTER</span>
                    <h2>လက်ရှိ Business မှာ အရင်ဆုံး စီမံရမယ့်အရာများ</h2>
                    <p>Completion percentage မပြပါဘူး။ တကယ်ဆုံးဖြတ်ချက်၊ Review သို့မဟုတ် Setup လိုတဲ့အရာတွေကိုပဲ Action အဖြစ် ပြပါတယ်။</p>
                </div>
            </div>

            @if($actions->isNotEmpty())
                <div class="pbr-business-attention-grid">
                    @foreach($actions->take(8) as $action)
                        <a href="{{ $action['url'] }}" class="pbr-business-attention-card {{ $action['level'] }}">
                            <span class="pbr-en-label">{{ strtoupper(str_replace('_', ' ', $action['domain'])) }}</span>
                            <strong>{{ $action['title_mm'] }}</strong>
                            <small>{{ $action['detail_mm'] }}</small>
                            <b>{{ $action['action_mm'] }}</b>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="pbr-business-healthy-banner">
                    <span>✓</span>
                    <div>
                        <strong>လက်ရှိ အရေးပေါ် Action မရှိပါ</strong>
                        <p>Working Draft မကျန်ဘဲ Funding Gap မရှိတဲ့ အခြေအနေဖြစ်ပါတယ်။ Business ပြောင်းလဲလာတိုင်း သက်ဆိုင်ရာ Area ကို Update လုပ်နိုင်ပါတယ်။</p>
                    </div>
                </div>
            @endif
        </section>

        <details class="pbr-business-settings">
            <summary>
                <div>
                    <span class="pbr-business-eyebrow">BUSINESS SETTINGS</span>
                    <strong>Business အခြေခံအချက်အလက်</strong>
                    <small>{{ $stageMm }} · {{ $currency }}</small>
                </div>
                <span class="pbr-business-settings-action">ပြင်ဆင်ရန် +</span>
            </summary>

            <div class="pbr-business-settings-body">
                <p>Currency နဲ့ Business Stage ကို Financial calculations၊ Valuation၊ Operating Rules နဲ့ PBR AI က ဒီ Business ရဲ့ default context အဖြစ် အသုံးပြုပါတယ်။</p>

                @if($canManageContext)
                    <form method="POST" action="{{ route('workspaces.business-context.update', $workspace) }}">
                        @csrf
                        @method('PUT')

                        <div class="pbr-context-grid">
                            <div class="pbr-tools-field">
                                <label for="business_stage">Partnership အခြေအနေ <span>Business Stage</span></label>
                                <select id="business_stage" name="business_stage" required>
                                    @foreach($businessStages as $value => $label)
                                        <option value="{{ $value }}" @selected(old('business_stage', $workspace->business_stage) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small>Business အသစ် စီစဉ်နေတာလား၊ ရှိပြီးသား Partnership ကို စီမံနေတာလား သတ်မှတ်ပါ။</small>
                            </div>

                            <div class="pbr-tools-field">
                                <label for="currency_code">အဓိက ငွေကြေး <span>Primary Currency</span></label>
                                <select id="currency_code" name="currency_code" required>
                                    @foreach($currencies as $value => $label)
                                        <option value="{{ $value }}" @selected(old('currency_code', $workspace->currency_code) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small>Financial modules အားလုံးရဲ့ default currency ဖြစ်ပါတယ်။</small>
                            </div>
                        </div>

                        <div class="pbr-context-actions">
                            <button type="submit" class="pbr-tools-primary-button">Business Settings သိမ်းရန်</button>
                        </div>
                    </form>
                @else
                    <div class="pbr-context-readonly">
                        <div><span>Partnership အခြေအနေ</span><strong>{{ $stageMm }}</strong></div>
                        <div><span>အဓိက ငွေကြေး</span><strong>{{ $currency }}</strong></div>
                    </div>
                @endif
            </div>
        </details>

        <section class="pbr-business-systems">
            <div class="pbr-business-section-head">
                <div>
                    <span class="pbr-business-eyebrow">OPERATING AREAS</span>
                    <h2>Partnership တစ်ခုလုံးရဲ့ Operating System</h2>
                    <p>တစ်ခုချင်းစီကို တစ်ကြိမ်ပြီးဆုံးသွားတဲ့ Lesson လိုမယူပါဘူး။ Business အခြေအနေပြောင်းတိုင်း Data၊ Decision နဲ့ Rule ကို ပြန်လည် Update လုပ်နိုင်ပါတယ်။</p>
                </div>
            </div>

            <div class="pbr-business-system-list">
                @foreach($systems as $system)
                    <details id="system-{{ $system['slug'] }}" class="pbr-business-system {{ $system['state']['key'] }}" @if($loop->first) open @endif>
                        <summary>
                            <div class="pbr-business-system-summary-main">
                                <span class="pbr-business-state {{ $system['state']['key'] }}">{{ $system['state']['label_mm'] }}</span>
                                <div>
                                    <small>{{ $system['name_en'] }}</small>
                                    <h3>{{ $system['name_mm'] }}</h3>
                                    <p>{{ $system['purpose_mm'] }}</p>
                                </div>
                            </div>
                            <div class="pbr-business-system-summary-meta">
                                <span>Active {{ $system['active_count'] }}</span>
                                @if($businessState['can_manage'])
                                    <span>Review {{ $system['working_count'] }}</span>
                                @endif
                                <b>ဖွင့်ရန် +</b>
                            </div>
                        </summary>

                        <div class="pbr-business-system-body">
                            <div class="pbr-business-module-grid">
                                @foreach($system['modules'] as $module)
                                    <a href="{{ $module['url'] }}" class="pbr-business-module-card {{ $module['state']['key'] }}">
                                        <div class="pbr-business-module-head">
                                            <span class="pbr-business-state {{ $module['state']['key'] }}">{{ $module['state']['label_mm'] }}</span>
                                            @if($module['active_revision'])
                                                <small>Active Revision {{ $module['active_revision'] }}</small>
                                            @elseif($module['draft_updated_at'])
                                                <small>Updated {{ optional($module['draft_updated_at'])->diffForHumans() }}</small>
                                            @endif
                                        </div>
                                        <h4>{{ $module['title_mm'] }}</h4>
                                        <span class="pbr-business-module-en">{{ $module['title_en'] }}</span>
                                        <p>{{ $module['purpose_mm'] }}</p>
                                        <b>{{ $module['action_mm'] }}</b>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="pbr-business-operating-summary">
            <div>
                <span class="pbr-business-eyebrow">CURRENT BUSINESS POSITION</span>
                <h2>Capital Snapshot</h2>
            </div>
            <div class="pbr-business-operating-summary-grid">
                <div><span>Capital Required</span><strong>{{ $currency }} {{ number_format((float) ($capital['capital_required'] ?? 0), 2) }}</strong></div>
                <div><span>Capital Secured</span><strong>{{ $currency }} {{ number_format((float) ($capital['capital_secured'] ?? 0), 2) }}</strong></div>
                <div><span>Funding Gap</span><strong>{{ $currency }} {{ number_format((float) ($capital['funding_gap'] ?? 0), 2) }}</strong></div>
                <div><span>Operating Records</span><strong>{{ $metrics['operating_record_count'] }}</strong></div>
            </div>
        </section>

        <section class="pbr-business-legal-note">
            <strong>Planning & Governance Note</strong>
            <p>{{ config('pbr_business_operating_system.legal_note_mm') }}</p>
        </section>
    </div>
</section>
@endsection
