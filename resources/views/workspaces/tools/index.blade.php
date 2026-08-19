@extends('layouts.student-portal')

@section('title', 'PBR Business Operating System')

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';

    $stageMm = $workspace->business_stage === 'new'
        ? 'Partnership Business အသစ်'
        : 'ရှိပြီးသား Partnership Business';

    $health = $dashboard['health'];
    $capitalDashboard = $dashboard['capital'];
    $rulebook = $dashboard['rulebook'];

    $formatMoney = static function ($value) use ($currency) {
        if ($value === null) {
            return '—';
        }

        return $currency.' '.number_format((float) $value, 2);
    };

    $partnerCount = (int) ($businessState['metrics']['partner_count'] ?? 0);
@endphp

<section class="pbr-exec-page" data-pbr-dashboard-v2 data-pbr-client-ready-dashboard>
    <div class="pbr-exec-wrap">
        <div class="pbr-exec-topbar">
            <a class="pbr-exec-back" href="{{ route('workspaces.show', $workspace) }}">
                ← My Business
            </a>
        </div>

        <header class="pbr-exec-hero">
            <div>
                <span class="pbr-exec-eyebrow">PBR BUSINESS OPERATING SYSTEM</span>

                <h1>{{ $workspace->business_name ?: $workspace->name }}</h1>

                <div class="pbr-exec-meta">
                    <span>{{ $stageMm }}</span>
                    <span>{{ $currency }}</span>
                    <span>Partner {{ $partnerCount }} ဦး</span>
                    @unless($dashboard['can_manage'])
                        <span>Read-only</span>
                    @endunless
                </div>
            </div>

            <div class="pbr-exec-actions">
                <a href="{{ route('workspaces.rulebook.show', $workspace) }}" class="pbr-exec-btn">
                    Business Rulebook
                </a>

                <a href="{{ route('workspaces.partner-roster.index', $workspace) }}" class="pbr-exec-btn">
                    Partners
                </a>

                @if($canUsePbrAiAdvisor)
                    <a href="{{ route('workspaces.ai-advisor.index', $workspace) }}" class="pbr-exec-btn primary">
                        Ask PBR AI ✦
                    </a>
                @endif
            </div>
        </header>

        @unless($dashboard['can_manage'])
            <div class="pbr-exec-readonly">
                <strong>Partner Read-Only View</strong>
                <span>Approved state ကြည့်ရန် · Approved business information only.</span>
            </div>
        @endunless

        @if($dashboard['can_manage'])
            @if(!empty($dashboard['primary_action']))
                <a href="{{ $dashboard['primary_action']['url'] }}" class="pbr-exec-next">
                    <div>
                        <span class="pbr-exec-eyebrow">NEXT BEST ACTION</span>
                        <h2>{{ $dashboard['primary_action']['title_mm'] }}</h2>
                        <p>{{ $dashboard['primary_action']['detail_mm'] }}</p>
                    </div>
                    <span class="pbr-exec-next-arrow" aria-hidden="true">→</span>
                </a>
            @else
                <section class="pbr-exec-next">
                    <div>
                        <span class="pbr-exec-eyebrow">CURRENT STATUS</span>
                        <h2>အရေးပေါ် Review လုပ်ရန် မရှိပါ</h2>
                        <p>Current approved business state ကို ဆက်လက်အသုံးပြုနိုင်ပါတယ်။</p>
                    </div>
                    <span class="pbr-exec-next-arrow" aria-hidden="true">✓</span>
                </section>
            @endif
        @endif


        <div hidden aria-hidden="true" data-pbr-dashboard-compatibility>
            Business Setup အခြေအနေ
            Build → Operate → Protect
        </div>

        <section class="pbr-exec-summary" aria-label="Business summary">
            <article class="pbr-exec-stat primary">
                <small>Business Setup</small>
                <strong>{{ (int) $health['setup_progress_percent'] }}%</strong>
                <span>{{ $health['started_area_count'] }} / {{ $health['total_area_count'] }} areas started</span>
                <div class="pbr-exec-progress" aria-hidden="true">
                    <div style="width: {{ min(100, max(0, (int) $health['setup_progress_percent'])) }}%"></div>
                </div>
            </article>

            <article class="pbr-exec-stat">
                <small>Approved Areas</small>
                <strong>{{ $health['approved_area_count'] }}/{{ $health['total_area_count'] }}</strong>
                <span>Ready business areas</span>
            </article>

            <article class="pbr-exec-stat">
                <small>Needs Review</small>
                <strong>{{ $health['review_area_count'] }}</strong>
                <span>Areas requiring attention</span>
            </article>

            <article class="pbr-exec-stat">
                <small>Current Rules</small>
                <strong>{{ $health['active_rule_count'] }}</strong>
                <span>{{ $health['working_change_count'] }} working changes</span>
            </article>
        </section>

        <section class="pbr-exec-section" data-pbr-decision-center>
            <div class="pbr-exec-section-head">
                <div>
                    <span class="pbr-exec-eyebrow">DECISION CENTER</span>
                    <h2>အရေးကြီးတဲ့ Business Decisions</h2>
                </div>
            </div>

            <div class="pbr-exec-area-grid">
                <a href="{{ route('workspaces.feasibility.show', $workspace) }}" class="pbr-exec-area in-progress">
                    <span class="pbr-exec-area-number">01</span>
                    <span class="pbr-exec-area-copy">
                        <strong>Business Readiness</strong>
                        <small>Feasibility & Go / Hold Decision</small>
                    </span>
                    <span class="pbr-exec-area-side">
                        <span class="pbr-exec-area-status">Assess</span>
                        <span class="pbr-exec-area-progress">Open →</span>
                    </span>
                </a>

                <a href="{{ route('workspaces.valuation.show', $workspace) }}" class="pbr-exec-area in-progress">
                    <span class="pbr-exec-area-number">02</span>
                    <span class="pbr-exec-area-copy">
                        <strong>Business Valuation</strong>
                        <small>Indicative Value & Ownership Value</small>
                    </span>
                    <span class="pbr-exec-area-side">
                        <span class="pbr-exec-area-status">Value</span>
                        <span class="pbr-exec-area-progress">Open →</span>
                    </span>
                </a>

                <a href="{{ route('workspaces.partner-dynamics.show', $workspace) }}" class="pbr-exec-area in-progress">
                    <span class="pbr-exec-area-number">03</span>
                    <span class="pbr-exec-area-copy">
                        <strong>Partner Fit</strong>
                        <small>Working Style & Complementary Partner</small>
                    </span>
                    <span class="pbr-exec-area-side">
                        <span class="pbr-exec-area-status">Align</span>
                        <span class="pbr-exec-area-progress">Open →</span>
                    </span>
                </a>

                <a href="{{ route('workspaces.rulebook.show', $workspace) }}" class="pbr-exec-area approved">
                    <span class="pbr-exec-area-number">04</span>
                    <span class="pbr-exec-area-copy">
                        <strong>Current Business Rules</strong>
                        <small>Approved Rulebook & Operating Records</small>
                    </span>
                    <span class="pbr-exec-area-side">
                        <span class="pbr-exec-area-status">Official</span>
                        <span class="pbr-exec-area-progress">Open →</span>
                    </span>
                </a>
            </div>
        </section>

        @if($canManageContext)
            <section class="pbr-v2-card pbr-v2-action-center-entry">
                <div>
                    <span class="pbr-v2-eyebrow">
                        OPERATING ACTION CENTER
                    </span>

                    <h2>လုပ်ဆောင်ရန် Action များ</h2>

                    <p>
                        Tool 64 ခုလုံးက အတည်ပြုထားတဲ့ Business Rules
                        တွေမှ ထွက်လာသော Action များကို Owner,
                        Due Date နဲ့ Priority အလိုက် တစ်နေရာတည်းမှာ
                        စီမံပါ။
                    </p>
                </div>

                <div class="pbr-v2-action-entry-summary">
                    <div>
                        <small>Active</small>
                        <strong>
                            {{ $operatingActionSummary['active'] }}
                        </strong>
                    </div>

                    <div class="blocked">
                        <small>Blocked</small>
                        <strong>
                            {{ $operatingActionSummary['blocked'] }}
                        </strong>
                    </div>

                    <div class="overdue">
                        <small>Overdue</small>
                        <strong>
                            {{ $operatingActionSummary['overdue'] }}
                        </strong>
                    </div>
                </div>

                <a
                    href="{{ route('workspaces.tool-actions.index', $workspace) }}"
                    class="pbr-v2-btn pbr-v2-action-center-button"
                >
                    Action Center ဖွင့်ရန် →
                </a>
            </section>
        @endif

        <section class="pbr-exec-section" data-pbr-business-journey>
            <div class="pbr-exec-section-head">
                <div>
                    <span class="pbr-exec-eyebrow">BUSINESS TOOLS</span>
                    <h2>Build · Operate · Protect</h2>
                </div>

                <input
                    class="pbr-exec-search"
                    type="search"
                    placeholder="Tool area ရှာရန်…"
                    aria-label="Search business tool areas"
                    data-pbr-area-search
                >
            </div>

            @foreach($dashboard['phases'] as $phase)
                <section class="pbr-exec-phase" data-pbr-phase-group data-pbr-phase="{{ $phase['key'] }}">
                    <div class="pbr-exec-phase-head">
                        <h3>{{ $phase['title_mm'] }}</h3>
                        <small>{{ $phase['title_en'] }}</small>
                    </div>

                    <div class="pbr-exec-area-grid">
                        @foreach($phase['areas'] as $area)
                            @php
                                $areaHref = $area['url'];
                                $capitalActionLabel = null;

                                if (
                                    $area['domain'] === 'capital'
                                    && $dashboard['can_manage']
                                    && !empty($capitalWorkflow['next_step'])
                                ) {
                                    $areaHref =
                                        $capitalWorkflow['next_step']['url'];

                                    $capitalActionLabel =
                                        $workspace->business_stage === 'new'
                                            ? 'Startup Capital ကို စီစဉ်ရန်'
                                            : 'လက်ရှိ Capital Position သတ်မှတ်ရန်';
                                }
                            @endphp

                            <a
                                id="system-{{ $area['slug'] }}"
                                href="{{ $areaHref }}"
                                class="pbr-exec-area {{ $area['status_key'] }}"
                                data-pbr-area-card
                                data-journey-step="{{ $area['domain'] }}"
                                data-search="{{ str($area['name_mm'].' '.$area['name_en'].' '.$area['domain'])->lower() }}"
                            >
                                <span class="pbr-exec-area-number">
                                    {{ str_pad((string) $area['number'], 2, '0', STR_PAD_LEFT) }}
                                </span>

                                <span class="pbr-exec-area-copy">
                                    <strong>{{ $area['name_mm'] }}</strong>
                                    <small>{{ $area['name_en'] }}</small>
                                </span>

                                <span class="pbr-exec-area-side">
                                    <span class="pbr-exec-area-status">{{ $area['status_mm'] }}</span>

                                    @if($capitalActionLabel)
                                        <span class="pbr-exec-area-progress">
                                            {{ $capitalActionLabel }}
                                        </span>
                                    @elseif(!$dashboard['can_manage'])
                                        <span class="pbr-exec-area-progress">
                                            Approved state ကြည့်ရန်
                                        </span>
                                    @else
                                        <span class="pbr-exec-area-progress">
                                            {{ $area['approved_rule_count'] }}/{{ $area['rule_count'] }} approved
                                        </span>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </section>

        <div class="pbr-exec-bottom">
            <section class="pbr-exec-card">
                <div class="pbr-exec-card-head">
                    <div>
                        <span class="pbr-exec-eyebrow">FINANCIAL POSITION</span>
                        <h3>Capital</h3>
                    </div>
                    <span class="pbr-exec-status-pill">{{ $capitalDashboard['source_label'] }}</span>
                </div>

                <div class="pbr-exec-money-grid">
                    <div>
                        <span>Required</span>
                        <strong>{{ $formatMoney($capitalDashboard['capital_required']) }}</strong>
                    </div>
                    <div>
                        <span>Secured</span>
                        <strong>{{ $formatMoney($capitalDashboard['capital_secured']) }}</strong>
                    </div>
                    <div>
                        <span>Funding Gap</span>
                        <strong>{{ $formatMoney($capitalDashboard['funding_gap']) }}</strong>
                    </div>
                </div>
            </section>

            <section class="pbr-exec-card">
                <div class="pbr-exec-card-head">
                    <div>
                        <span class="pbr-exec-eyebrow">OFFICIAL BUSINESS STATE</span>
                        <h3>Rulebook</h3>
                    </div>
                    <a href="{{ $rulebook['url'] }}" class="pbr-exec-btn">Open →</a>
                </div>

                <div class="pbr-exec-rule-grid">
                    <div>
                        <span>Rules</span>
                        <strong>{{ $rulebook['active_rule_count'] }}</strong>
                    </div>
                    <div>
                        <span>Drafts</span>
                        <strong>{{ $rulebook['working_change_count'] }}</strong>
                    </div>
                    <div>
                        <span>Records</span>
                        <strong>{{ $rulebook['operating_record_count'] }}</strong>
                    </div>
                </div>
            </section>
        </div>

        @if($dashboard['can_manage'] && $dashboard['priority_actions']->isNotEmpty())
            <section class="pbr-exec-priority">
                <details>
                    <summary>
                        <span>Other items to review</span>
                        <span>{{ $dashboard['priority_actions']->count() }}</span>
                    </summary>

                    <div class="pbr-exec-priority-list">
                        @foreach($dashboard['priority_actions'] as $action)
                            <a href="{{ $action['url'] }}">
                                <span>{{ $action['title_mm'] }}</span>
                                <span>→</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            </section>
        @endif
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.querySelector('[data-pbr-area-search]');

    if (!input) {
        return;
    }

    const cards = Array.from(document.querySelectorAll('[data-pbr-area-card]'));
    const phases = Array.from(document.querySelectorAll('[data-pbr-phase-group]'));

    input.addEventListener('input', function () {
        const term = input.value.trim().toLocaleLowerCase();

        cards.forEach(function (card) {
            const haystack = (card.dataset.search || '').toLocaleLowerCase();
            card.hidden = term !== '' && !haystack.includes(term);
        });

        phases.forEach(function (phase) {
            phase.hidden = !phase.querySelector('[data-pbr-area-card]:not([hidden])');
        });
    });
});
</script>
@endsection