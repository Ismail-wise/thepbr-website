@extends('layouts.student-portal')
@section('title', 'Partner Contributions')
@section('content')
<main class="c2-page">
    <a href="{{ route('workspaces.tools.index', $workspace) }}">Business Tools သို့</a>
    <section class="c2-page-hero" aria-labelledby="c2-title">
        <div class="c2-hero-grid">
            <div class="c2-hero-copy">
                <span class="c2-eyebrow">CONTRIBUTION &amp; VALUE</span>

                <h1 id="c2-title">Partner Contributions</h1>

                <p class="c2-hero-lead">
                    Partner တစ်ဦးချင်းစီရဲ့ Cash, Time &amp; Skill,
                    Property &amp; Asset နဲ့ IP &amp; Intangible
                    Contribution တွေကို evidence နဲ့တကွ
                    တန်ဖိုးသတ်မှတ်၊ review လုပ်၊ approve လုပ်ပြီး
                    Accepted Contribution Value အဖြစ် စနစ်တကျ
                    မှတ်တမ်းတင်နိုင်ပါတယ်။
                </p>

                <div class="c2-workspace-context">
                    <span>Business Workspace</span>
                    <strong>{{ $workspace->name }}</strong>
                </div>
            </div>

            <div class="c2-hero-actions">
                @if($canManage)
                    <a class="c2-button c2-button-primary"
                       href="#c2-management">
                        Contribution Workspace
                    </a>
                @endif

                <a class="c2-button c2-button-secondary"
                   href="#c2-current">
                    Approved Contributions
                </a>
            </div>
        </div>

        <div class="c2-type-strip" aria-label="Contribution types">
            <span>Cash</span>
            <span>Time &amp; Skill</span>
            <span>Property &amp; Asset</span>
            <span>IP &amp; Intangible</span>
        </div>

        <div class="c2-boundary">
            <strong>Accepted Value First</strong>
            <p>
                ဒီအဆင့်မှာ Contribution Value ကိုပဲ finalize လုပ်ပါတယ်။
                Shares, Ownership % နဲ့ Share Value ကို ဒီနေရာမှာ
                မတွက်ပါ။ Approved Accepted Contribution Value ကို
                Ownership &amp; Equity process ဆီ လွှဲပေးပါတယ်။
            </p>
        </div>
    </section>

    <section class="c2-flow" aria-labelledby="c2-flow-title">
        <div class="c2-flow-heading">
            <span class="c2-eyebrow">BUSINESS WORKFLOW</span>
            <h2 id="c2-flow-title">Contribution Planning Flow</h2>

            <p>
                Contribution တစ်ခုဟာ စာရင်းထည့်ထားရုံနဲ့
                final value မဖြစ်ပါဘူး။ Valuation, Evidence,
                Delivery နဲ့ Approval ပြည့်စုံမှ Accepted
                Contribution Value ဖြစ်လာပါတယ်။
            </p>
        </div>

        <div class="c2-flow-grid">
            <article class="c2-flow-step">
                <span>01</span>
                <strong>Setup</strong>
                <small>
                    Valuation date, currency, contribution period
                    နဲ့ approvers သတ်မှတ်ပါ။
                </small>
            </article>

            <article class="c2-flow-step">
                <span>02</span>
                <strong>Partners</strong>
                <small>
                    Contribution ပေးမယ့် Partner တွေကိုရွေးပါ။
                </small>
            </article>

            <article class="c2-flow-step">
                <span>03</span>
                <strong>Contributions</strong>
                <small>
                    Cash, Time &amp; Skill, Asset သို့မဟုတ်
                    IP contribution ကို record လုပ်ပါ။
                </small>
            </article>

            <article class="c2-flow-step">
                <span>04</span>
                <strong>Valuation</strong>
                <small>
                    Proposed Value နဲ့ valuation basis ကို
                    စစ်ဆေးပါ။
                </small>
            </article>

            <article class="c2-flow-step">
                <span>05</span>
                <strong>Evidence &amp; Conditions</strong>
                <small>
                    Evidence, delivery dates နဲ့ conditions
                    ကို confirm လုပ်ပါ။
                </small>
            </article>

            <article class="c2-flow-step">
                <span>06</span>
                <strong>Approval</strong>
                <small>
                    Designated approvers တွေရဲ့ decision ကို
                    record လုပ်ပါ။
                </small>
            </article>

            <article class="c2-flow-step c2-flow-final">
                <span>07</span>
                <strong>Contribution Matrix</strong>
                <small>
                    Accepted Contribution Value ကို final
                    business result အဖြစ်ကြည့်ပါ။
                </small>
            </article>
        </div>
    </section>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())
        <div class="c2-card c2-error" role="alert">
            <strong>သိမ်းဆည်းမှု မအောင်မြင်ပါ။</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="c2-card" id="c2-current">
        <h2>Approved Contributions</h2>
        @unless($canManage)
            <p class="c2-note">
                Approved contribution records ကိုသာ
                ကြည့်ရှုနိုင်ပါသည်။
            </p>
        @endunless
        @if($current === null)
            <div class="c2-empty-state">
                <strong>Approved contributions မရှိသေးပါ။</strong>
                <p>
                    Contribution Plan တစ်ခုဖန်တီးပြီး
                    valuation, evidence, delivery နဲ့ approvals
                    ပြည့်စုံသွားတဲ့အခါ final accepted result ကို
                    ဒီနေရာမှာတွေ့ရပါမယ်။
                </p>
            </div>
        @else
            <p>Revision {{ $current['canonical_revision']['revision'] }} · {{ $current['currency_code'] }}</p>
            <div class="c2-total">
                Total Accepted Contribution Value:
                {{ $current['total_accepted_contribution_value'] }} {{ $current['currency_code'] }}
            </div>
            <p class="c2-note">Matrix နှင့် total တွင် accepted rows များ၏ တန်ဖိုးကိုသာ ထည့်တွက်ထားပါသည်။</p>
            <h3>Contribution Matrix</h3>
            <div class="c2-scroll"><table>
                <thead><tr>
                    <th>Partner</th><th>Cash</th><th>Time / Skill</th>
                    <th>Property / Asset</th><th>IP / Intangible</th><th>Accepted Total</th>
                </tr></thead>
                <tbody>
                @foreach($current['contribution_matrix']['partners'] as $row)
                    <tr>
                        <td>{{ $row['partner_snapshot']['display_name'] }}</td>
                        @foreach(['cash','time_skill','property_asset','ip_intangible','accepted_total'] as $key)
                            <td>{{ $row[$key] }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><th>Total</th>
                    @foreach(['cash','time_skill','property_asset','ip_intangible','accepted_total'] as $key)
                        <th>{{ $current['contribution_matrix']['totals'][$key] }}</th>
                    @endforeach
                </tr></tfoot>
            </table></div>
            <h3>Contribution Register</h3>
            <div class="c2-scroll"><table>
                <thead><tr>
                    <th>Partner / Item</th><th>Type</th><th>Status</th><th>Agreed Value</th>
                    <th>Recorded Acceptance Value</th><th>Evidence / Delivery / Conditions</th>
                </tr></thead>
                <tbody>
                @foreach($current['contribution_register'] as $row)
                    <tr>
                        <td>{{ $row['partner_snapshot']['display_name'] }}<br>{{ $row['description'] }}
                            <br><small>{{ $row['owner_reference'] }}</small></td>
                        <td>{{ $row['contribution_type'] }}</td>
                        <td>{{ $row['approval_status'] }}</td>
                        <td>{{ $row['agreed_value'] ?? '—' }}</td>
                        <td>{{ $row['accepted_value'] ?? '—' }}
                            @if($row['approval_status'] !== 'accepted')
                                <br><small>Total တွင် မပါဝင်ပါ။</small>
                            @endif
                        </td>
                        <td>
                            @foreach($row['evidence_references'] as $reference)<div>{{ $reference }}</div>@endforeach
                            <div>{{ $row['delivery_date'] ?? '—' }}</div>
                            <div>{{ $row['condition'] }}</div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    @if($canManage)
        <section class="c2-card" id="c2-management">
            <h2>Contribution Workspace</h2>
            @if($current === null)
                <form method="POST" action="{{ route('workspaces.tools.v2.contribution.store', $workspace) }}">
                    @csrf
                    <button type="submit">New Contribution Plan</button>
                </form>
            @else
                <p>Approved register ပြင်ဆင်မှုအတွက် review/amendment လမ်းကြောင်းကို အသုံးပြုရပါမည်။</p>
            @endif
            <ul>
                @foreach($queue['items'] as $entry)
                    <li><a href="{{ route('workspaces.tools.v2.contribution.show', ['workspace'=>$workspace,'artifact_id'=>$entry['id']]) }}">
                        Draft #{{ $entry['id'] }} · {{ $entry['state'] }}
                    </a></li>
                @endforeach
            </ul>
            @if($queue['next_before_id'] !== null)
                <a href="{{ route('workspaces.tools.v2.contribution.show', ['workspace'=>$workspace,'before_id'=>$queue['next_before_id']]) }}">More Contribution Plans</a>
            @endif
        </section>

        @if($artifact !== null)
            @php
                $payload = $artifact['payload'];
                $aid = $artifact['id'];
                $version = $payload['edit_version'];
                $setup = $payload['setup'] ?? [];
                $selectedPartners = $payload['partners'] ?? [];
                $items = $payload['items'] ?? [];
            @endphp
            <section class="c2-card">
                <h2>Draft #{{ $aid }} · {{ $artifact['state'] }}</h2>
                <p>Edit version {{ $version }}</p>
                @if($artifact['state'] !== 'working_draft')
                    <p>Submitted proposal ကို ဒီနေရာမှ ပြင်ဆင်၍မရပါ။</p>
                @else
                    @php $formKey = 'setup-'.$aid; @endphp
                    <details open>
                        <summary>1. Setup</summary>
                        <p class="c2-note">Setup ပြောင်းလဲခြင်းသည် active items များကို proposed အဖြစ် ပြန်ဖွင့်ပြီး approvals ကို ပြန်ယူရန် လိုအပ်စေနိုင်ပါသည်။</p>
                        <form method="POST" action="{{ route('workspaces.tools.v2.contribution.setup', ['workspace'=>$workspace,'artifactId'=>$aid]) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="_form" value="{{ $formKey }}">
                            <input type="hidden" name="edit_version" value="{{ $version }}">
                            <input type="hidden" name="setup[currency_code]" value="{{ $workspace->currency_code }}">
                            <p>Currency: {{ $workspace->currency_code }}</p>
                            <div class="c2-grid">
                            @foreach([
                                'valuation_date'=>'Valuation Date',
                                'contribution_period.start_date'=>'Period Start',
                                'contribution_period.end_date'=>'Period End',
                                'valuation_owner_reference'=>'Valuation Owner',
                            ] as $field=>$label)
                                @php
                                    $name = 'setup['.str_replace('.', '][', $field).']';
                                    $value = old('_form') === $formKey ? old('setup.'.$field) : data_get($setup, $field, '');
                                @endphp
                                <label>{{ $label }}
                                    <input name="{{ $name }}" type="{{ $field === 'valuation_owner_reference' ? 'text' : 'date' }}" value="{{ $value }}" required>
                                </label>
                            @endforeach
                            </div>
                            <fieldset><legend>Designated Approvers</legend>
                                @php
                                    $chosen = old('_form') === $formKey
                                        ? old('setup.approver_user_ids', [])
                                        : ($setup['approver_user_ids'] ?? []);
                                @endphp
                                @foreach($approvers as $person)
                                    <label><input type="checkbox" name="setup[approver_user_ids][]" value="{{ $person->id }}" @checked(in_array($person->id, $chosen))>
                                        {{ $person->name }}
                                    </label>
                                @endforeach
                            </fieldset>
                            <button type="submit">Save Setup</button>
                        </form>
                    </details>
                    <hr>
                    <details>
                        <summary>2. Select Partners</summary>
                        <a href="{{ route('workspaces.partner-roster.index', $workspace) }}">Partner Roster</a>
                        <form method="POST" action="{{ route('workspaces.tools.v2.contribution.partners', ['workspace'=>$workspace,'artifactId'=>$aid]) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="edit_version" value="{{ $version }}">
                            @foreach($profiles as $profile)
                                <label><input type="checkbox" name="profile_ids[]" value="{{ $profile->id }}" @checked(in_array($profile->id, array_column($selectedPartners, 'profile_id')))>
                                    {{ $profile->display_name }} · {{ $profile->status }}
                                </label>
                            @endforeach
                            <button type="submit">Save Partners</button>
                        </form>
                    </details>
                @endif
            </section>

            @include('workspaces.v2.contribution-lifecycle')

            <section class="c2-card">
                <h2>3. Contribution Items</h2>
                <p class="c2-note">Draft item values များသည် approved-current total မဟုတ်ပါ။ Item ပြန်ပြင်လျှင် proposed အဖြစ်ပြန်ဝင်ပြီး review နှင့် approvals ပြန်ယူရပါမည်။</p>
                @foreach($items as $item)
                    <p><strong>{{ $item['description'] ?? $item['id'] }}</strong>
                        · {{ $item['contribution_type'] }} · {{ $item['status'] }}</p>
                @endforeach

                @if($artifact['state'] === 'working_draft')
                    @php
                        $editors = [];
                        foreach ($items as $item) {
                            $editors[] = ['id'=>$item['id'], 'type'=>$item['contribution_type'], 'values'=>$item];
                        }
                        foreach (array_keys($fieldsByType) as $type) {
                            $editors[] = ['id'=>null, 'type'=>$type, 'values'=>[]];
                        }
                    @endphp
                    @foreach($editors as $editor)
                        @php
                            $type = $editor['type'];
                            $formKey = 'item-'.$aid.'-'.($editor['id'] ?? 'new-'.$type);
                            $values = $editor['values'];
                            $params = ['workspace'=>$workspace,'artifactId'=>$aid];
                            if ($editor['id'] !== null) { $params['itemId'] = $editor['id']; }
                            $action = route($editor['id'] === null
                                ? 'workspaces.tools.v2.contribution.items.store'
                                : 'workspaces.tools.v2.contribution.items.update', $params);
                        @endphp
                        <details class="c2-card">
                            <summary>{{ $editor['id'] === null ? 'Add' : 'Revise' }} · {{ $type }} · {{ $values['description'] ?? '' }}</summary>
                            <form method="POST" action="{{ $action }}">
                                @csrf
                                @if($editor['id'] !== null) @method('PUT') @endif
                                <input type="hidden" name="_form" value="{{ $formKey }}">
                                <input type="hidden" name="edit_version" value="{{ $version }}">
                                <input type="hidden" name="item[contribution_type]" value="{{ $type }}">
                                @php
                                    $chosenKey = old('_form') === $formKey
                                        ? old('item.partner_key') : ($values['partner_key'] ?? '');
                                @endphp
                                <label>Partner
                                    <select name="item[partner_key]" required>
                                        <option value="">Select partner</option>
                                        @foreach($selectedPartners as $partner)
                                            <option value="{{ $partner['partner_key'] }}" @selected($chosenKey === $partner['partner_key'])>{{ $partner['display_name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="c2-grid">
                                @foreach($fieldsByType[$type] as $field)
                                    @php
                                        $value = $values[$field] ?? '';
                                        if ($field === 'evidence_references') { $value = implode("\n", $values[$field] ?? []); }
                                        if ($field === 'ownership_transferred' && is_bool($value)) { $value = $value ? '1' : '0'; }
                                        if (old('_form') === $formKey) { $value = old('item.'.$field, ''); }
                                        $label = ucwords(str_replace('_', ' ', $field));
                                    @endphp
                                    <label>{{ $label }}
                                        @if($field === 'ownership_transferred')
                                            <select name="item[{{ $field }}]">
                                                <option value="">Select</option>
                                                <option value="1" @selected((string)$value === '1')>Yes — asset ownership transferred</option>
                                                <option value="0" @selected((string)$value === '0')>No — usage only</option>
                                            </select>
                                        @elseif($field === 'subtype')
                                            <select name="item[subtype]">
                                                <option value="">Select subtype</option>
                                                @foreach(config('pbr_v2_contribution.intangible_subtypes') as $subtype)
                                                    <option value="{{ $subtype }}" @selected($value === $subtype)>{{ $subtype }}</option>
                                                @endforeach
                                            </select>
                                        @elseif(in_array($field, ['description','conditions','notes','evidence_references']))
                                            <textarea name="item[{{ $field }}]" rows="3">{{ $value }}</textarea>
                                            @if($field === 'evidence_references')<small>Evidence reference တစ်ခုကို တစ်ကြောင်းစီ ရေးပါ။</small>@endif
                                        @else
                                            <input name="item[{{ $field }}]" type="{{ str_ends_with($field, '_date') ? 'date' : 'text' }}" value="{{ $value }}">
                                        @endif
                                    </label>
                                @endforeach
                                </div>
                                <button type="submit">{{ $editor['id'] === null ? 'Add Contribution Item' : 'Save Item Revision' }}</button>
                            </form>
                        </details>
                    @endforeach
                @endif
            </section>
        @endif
    @endif
</main>
@endsection
