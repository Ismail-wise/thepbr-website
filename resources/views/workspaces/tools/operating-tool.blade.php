@extends('layouts.student-portal')

@section('title', $definition['title_mm'] ?? $tool->title_en)

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $chapterNumber = (int) ($tool->chapter?->chapter_number ?? ($definition['chapter'] ?? 0));
    $formatValue = static function ($value, $format) use ($currency) {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($format) {
            'money' => $currency.' '.number_format((float) $value, 2),
            'percent' => number_format((float) $value, 2).'%',
            'units' => number_format((float) $value, 2).' Units',
            'months' => number_format((float) $value, 2).' Months',
            'days' => number_format((float) $value, 0).' Days',
            'number' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            default => (string) $value,
        };
    };
@endphp

<section class="pbr-os-page">
    <div class="portal-wrap pbr-os-wrap">
        <nav class="pbr-os-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('workspaces.show', $workspace) }}">Business Control Center</a>
            <span>›</span>
            <a href="{{ route('workspaces.tools.index', $workspace) }}">10-Chapter System</a>
            <span>›</span>
            <span>Chapter {{ $chapterNumber }}</span>
        </nav>

        <header class="pbr-os-hero">
            <div class="pbr-os-hero-copy">
                <div class="pbr-os-kickers">
                    <span class="pbr-os-chapter-pill">Chapter {{ str_pad((string) $chapterNumber, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="pbr-os-type-pill">{{ ucfirst($tool->tool_type) }}</span>
                    @if($latestAgreedOutput)
                        <span class="pbr-os-agreed-pill">✓ Agreed Rule ရှိပြီး</span>
                    @endif
                </div>

                <h1>{{ $definition['title_mm'] ?? $tool->title_mm ?? $tool->title_en }}</h1>
                <p class="pbr-os-en-title">{{ $tool->title_en }}</p>
                <p class="pbr-os-purpose">{{ $definition['purpose_mm'] ?? $tool->description }}</p>
            </div>

            <aside class="pbr-os-business-context">
                <span>လက်ရှိ Business</span>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <div>
                    <small>{{ $workspace->business_stage === 'new' ? 'Planning a New Partnership' : 'Managing an Existing Partnership' }}</small>
                    <small>{{ $currency }}</small>
                </div>
            </aside>
        </header>

        @if(session('status'))
            <div class="pbr-os-alert success">{{ session('status') }}</div>
        @endif

        @unless($canManage)
            <div class="pbr-os-readonly-banner">
                <div>
                    <strong>Partner Read-Only View</strong>
                    <p>ဒီ Business ရဲ့ Owner/Admin က အတည်ပြုထားတဲ့ <b>Agreed Business Rule</b> ကိုသာ မြင်ရပါတယ်။ Draft, private scenario နဲ့ owner-only inputs တွေကို မပြပါဘူး။</p>
                </div>
                <span>Permission Safe</span>
            </div>
        @endunless

        <div class="pbr-os-layout {{ $canManage ? '' : 'readonly' }}">
            @if($canManage)
                <aside class="pbr-os-sidebar">
                    <div class="pbr-os-side-card">
                        <span class="pbr-os-side-label">Workflow</span>
                        <ol class="pbr-os-steps">
                            <li class="active"><span>1</span><div><b>Data ထည့်ပါ</b><small>Actual business information</small></div></li>
                            <li><span>2</span><div><b>Calculate / Review</b><small>Logic + warnings + comparison</small></div></li>
                            <li><span>3</span><div><b>Save Draft</b><small>Scenario မပျောက်အောင်သိမ်း</small></div></li>
                            <li><span>4</span><div><b>Approve</b><small>Agreed Business Rule ဖြစ်မယ်</small></div></li>
                        </ol>
                    </div>

                    <div class="pbr-os-side-card">
                        <div class="pbr-os-side-head">
                            <div>
                                <span class="pbr-os-side-label">Saved Scenarios</span>
                                <strong>{{ $drafts->count() }} Drafts</strong>
                            </div>
                            <a href="{{ route('workspaces.tools.operating.show', [$workspace, $tool->slug]) }}">New</a>
                        </div>

                        @forelse($drafts as $draft)
                            <a
                                class="pbr-os-draft-link {{ $activeSession?->id === $draft->id ? 'active' : '' }}"
                                href="{{ route('workspaces.tools.operating.show', [$workspace, $tool->slug, 'session' => $draft->id]) }}"
                            >
                                <span>{{ $draft->scenario_name ?: 'Untitled Scenario' }}</span>
                                <small>{{ optional($draft->last_saved_at)->diffForHumans() }}</small>
                            </a>
                        @empty
                            <p class="pbr-os-empty-small">Draft မရှိသေးပါ။ Form ဖြည့်ပြီး Save Draft လုပ်ပါ။</p>
                        @endforelse
                    </div>

                    @if($outputHistory->isNotEmpty())
                        <div class="pbr-os-side-card">
                            <span class="pbr-os-side-label">Rule History</span>
                            <div class="pbr-os-history-list">
                                @foreach($outputHistory as $output)
                                    <div>
                                        <span class="{{ $output->status === 'agreed' ? 'agreed' : 'draft' }}">{{ strtoupper($output->status) }}</span>
                                        <b>Revision {{ $output->revision }}</b>
                                        <small>{{ optional($output->generated_at)->format('d M Y, H:i') }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>
            @endif

            <main class="pbr-os-main">
                @if($canManage)
                    <section class="pbr-os-panel pbr-os-input-panel">
                        <div class="pbr-os-panel-head">
                            <div>
                                <span class="portal-kicker">Business Inputs</span>
                                <h2>{{ $activeSession ? 'Scenario ကိုပြင်နေပါတယ်' : 'Scenario အသစ်တည်ဆောက်ပါ' }}</h2>
                                <p>Tool တစ်ခုချင်းစီမှာ လိုအပ်တဲ့ data ပဲမေးထားပါတယ်။ Empty field မဖြည့်ချင်ရင် 0 / blank ထားနိုင်တဲ့နေရာတွေရှိပါတယ်။</p>
                            </div>
                            @if($activeSession)
                                <span class="pbr-os-session-badge">Draft #{{ $activeSession->id }}</span>
                            @endif
                        </div>

                        <form
                            method="POST"
                            action="{{ route('workspaces.tools.operating.calculate', [$workspace, $tool->slug]) }}"
                            class="pbr-os-form"
                            id="pbrOperatingToolForm"
                        >
                            @csrf
                            <input type="hidden" name="tool_session_id" value="{{ $activeSession?->id }}">

                            <div class="pbr-os-fields">
                                @foreach($definition['fields'] ?? [] as $field)
                                    @php
                                        $fieldName = $field['name'];
                                        $fieldType = $field['type'] ?? 'text';
                                        $fieldValue = old($fieldName, $input[$fieldName] ?? ($field['default'] ?? ''));
                                    @endphp

                                    @if($fieldType === 'repeater')
                                        <div class="pbr-os-field pbr-os-field-wide pbr-os-repeater" data-pbr-repeater="{{ $fieldName }}">
                                            <div class="pbr-os-field-heading">
                                                <div>
                                                    <label>{{ $field['label_mm'] }}</label>
                                                    <span>{{ $field['label_en'] }}</span>
                                                </div>
                                                <button type="button" class="pbr-os-add-row" data-repeater-add>+ Row ထည့်ရန်</button>
                                            </div>
                                            @if(!empty($field['help_mm']))
                                                <p class="pbr-os-help">{{ $field['help_mm'] }}</p>
                                            @endif

                                            @php
                                                $repeaterRows = is_array($fieldValue) ? array_values($fieldValue) : [];
                                                if (count($repeaterRows) === 0) {
                                                    $repeaterRows = [[]];
                                                }
                                            @endphp

                                            <div class="pbr-os-repeater-rows" data-repeater-rows>
                                                @foreach($repeaterRows as $rowIndex => $row)
                                                    <div class="pbr-os-repeater-row" data-repeater-row>
                                                        <span class="pbr-os-row-number">{{ $rowIndex + 1 }}</span>
                                                        @foreach($field['columns'] ?? [] as $column)
                                                            @php
                                                                $columnName = $column['name'];
                                                                $columnValue = $row[$columnName] ?? '';
                                                                $columnType = $column['type'] ?? 'text';
                                                            @endphp
                                                            <div class="pbr-os-mini-field">
                                                                <label>{{ $column['label_mm'] ?? $column['label_en'] }}</label>
                                                                @if($columnType === 'select')
                                                                    <select name="{{ $fieldName }}[{{ $rowIndex }}][{{ $columnName }}]">
                                                                        @foreach($column['options'] ?? [] as $optionValue => $optionLabel)
                                                                            <option value="{{ $optionValue }}" @selected((string) $columnValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                @else
                                                                    <input
                                                                        type="{{ $columnType === 'number' ? 'number' : 'text' }}"
                                                                        name="{{ $fieldName }}[{{ $rowIndex }}][{{ $columnName }}]"
                                                                        value="{{ $columnValue }}"
                                                                        @if($columnType === 'number')
                                                                            step="{{ $column['step'] ?? '0.01' }}"
                                                                            @isset($column['min']) min="{{ $column['min'] }}" @endisset
                                                                            @isset($column['max']) max="{{ $column['max'] }}" @endisset
                                                                            inputmode="decimal"
                                                                        @endif
                                                                    >
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                        <button type="button" class="pbr-os-remove-row" data-repeater-remove aria-label="Remove row">×</button>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <template data-repeater-template>
                                                <div class="pbr-os-repeater-row" data-repeater-row>
                                                    <span class="pbr-os-row-number">__NUMBER__</span>
                                                    @foreach($field['columns'] ?? [] as $column)
                                                        @php $columnType = $column['type'] ?? 'text'; @endphp
                                                        <div class="pbr-os-mini-field">
                                                            <label>{{ $column['label_mm'] ?? $column['label_en'] }}</label>
                                                            @if($columnType === 'select')
                                                                <select name="{{ $fieldName }}[__INDEX__][{{ $column['name'] }}]">
                                                                    @foreach($column['options'] ?? [] as $optionValue => $optionLabel)
                                                                        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @else
                                                                <input
                                                                    type="{{ $columnType === 'number' ? 'number' : 'text' }}"
                                                                    name="{{ $fieldName }}[__INDEX__][{{ $column['name'] }}]"
                                                                    value=""
                                                                    @if($columnType === 'number')
                                                                        step="{{ $column['step'] ?? '0.01' }}"
                                                                        @isset($column['min']) min="{{ $column['min'] }}" @endisset
                                                                        @isset($column['max']) max="{{ $column['max'] }}" @endisset
                                                                        inputmode="decimal"
                                                                    @endif
                                                                >
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                    <button type="button" class="pbr-os-remove-row" data-repeater-remove aria-label="Remove row">×</button>
                                                </div>
                                            </template>
                                        </div>

                                    @elseif($fieldType === 'checklist')
                                        <div class="pbr-os-field pbr-os-field-wide">
                                            <div class="pbr-os-field-heading">
                                                <div>
                                                    <label>{{ $field['label_mm'] }}</label>
                                                    <span>{{ $field['label_en'] }}</span>
                                                </div>
                                            </div>
                                            @if(!empty($field['help_mm']))
                                                <p class="pbr-os-help">{{ $field['help_mm'] }}</p>
                                            @endif
                                            <div class="pbr-os-checklist">
                                                @foreach($field['items'] ?? [] as $itemKey => $itemLabel)
                                                    <label class="pbr-os-check-item">
                                                        <input type="hidden" name="{{ $fieldName }}[{{ $itemKey }}]" value="0">
                                                        <input
                                                            type="checkbox"
                                                            name="{{ $fieldName }}[{{ $itemKey }}]"
                                                            value="1"
                                                            @checked((bool) ($fieldValue[$itemKey] ?? false))
                                                        >
                                                        <span><b>{{ $itemLabel }}</b><small>ပြီးထားရင် check လုပ်ပါ</small></span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                    @else
                                        <div class="pbr-os-field {{ $fieldType === 'textarea' ? 'pbr-os-field-wide' : '' }}">
                                            <label for="{{ $fieldName }}">
                                                {{ $field['label_mm'] }}
                                                <span>{{ $field['label_en'] }}</span>
                                            </label>

                                            @if($fieldType === 'select')
                                                <select id="{{ $fieldName }}" name="{{ $fieldName }}">
                                                    @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" @selected((string) $fieldValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($fieldType === 'textarea')
                                                <textarea id="{{ $fieldName }}" name="{{ $fieldName }}" rows="4">{{ $fieldValue }}</textarea>
                                            @else
                                                <input
                                                    id="{{ $fieldName }}"
                                                    name="{{ $fieldName }}"
                                                    type="{{ in_array($fieldType, ['number', 'date'], true) ? $fieldType : 'text' }}"
                                                    value="{{ $fieldValue }}"
                                                    @if($fieldType === 'number')
                                                        step="{{ $field['step'] ?? '0.01' }}"
                                                        @isset($field['min']) min="{{ $field['min'] }}" @endisset
                                                        @isset($field['max']) max="{{ $field['max'] }}" @endisset
                                                        inputmode="decimal"
                                                    @endif
                                                >
                                            @endif

                                            @if(!empty($field['help_mm']))
                                                <small class="pbr-os-help">{{ $field['help_mm'] }}</small>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            <div class="pbr-os-form-actions">
                                <button type="submit" class="pbr-os-btn primary">Calculate / Review</button>

                                <div class="pbr-os-save-cluster">
                                    <label for="scenario_name">Scenario အမည်</label>
                                    <input
                                        id="scenario_name"
                                        type="text"
                                        name="scenario_name"
                                        maxlength="120"
                                        value="{{ old('scenario_name', $activeSession?->scenario_name ?? '') }}"
                                        placeholder="ဥပမာ: Base Plan 2026"
                                    >
                                    <button
                                        type="submit"
                                        class="pbr-os-btn secondary"
                                        formaction="{{ route('workspaces.tools.operating.save', [$workspace, $tool->slug]) }}"
                                    >Save Draft</button>
                                </div>
                            </div>
                        </form>
                    </section>
                @endif

                @if($result)
                    <section class="pbr-os-panel pbr-os-result-panel" id="result">
                        <div class="pbr-os-result-hero">
                            <span>{{ $result['headline']['label'] ?? 'Result' }}</span>
                            <strong>{{ $formatValue($result['headline']['value'] ?? null, $result['headline']['format'] ?? 'text') }}</strong>
                            <small>Based on the information entered for this scenario</small>
                        </div>

                        @if(!empty($result['metrics']))
                            <div class="pbr-os-metrics">
                                @foreach($result['metrics'] as $metric)
                                    <article>
                                        <span>{{ $metric['label'] }}</span>
                                        <strong>{{ $formatValue($metric['value'] ?? null, $metric['format'] ?? 'text') }}</strong>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @foreach($result['tables'] ?? [] as $table)
                            <div class="pbr-os-result-table-wrap">
                                <div class="pbr-os-table-head">
                                    <h3>{{ $table['title'] ?? 'Details' }}</h3>
                                    <span>{{ count($table['rows'] ?? []) }} rows</span>
                                </div>
                                <div class="pbr-os-table-scroll">
                                    <table class="pbr-os-result-table">
                                        <thead>
                                            <tr>
                                                @foreach($table['columns'] ?? [] as $columnLabel)
                                                    <th>{{ $columnLabel }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($table['rows'] ?? [] as $row)
                                                <tr>
                                                    @foreach($table['columns'] ?? [] as $columnKey => $columnLabel)
                                                        @php $cell = $row[$columnKey] ?? null; @endphp
                                                        <td>
                                                            @if(is_numeric($cell) && str_contains(strtolower($columnLabel), '%'))
                                                                {{ number_format((float) $cell, 2) }}%
                                                            @elseif(is_numeric($cell))
                                                                {{ number_format((float) $cell, 2) }}
                                                            @else
                                                                {{ $cell ?? '—' }}
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @empty
                                                <tr><td colspan="{{ count($table['columns'] ?? []) }}">Data မရှိသေးပါ။</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach

                        @if(!empty($result['warnings']))
                            <div class="pbr-os-insight warning">
                                <div class="pbr-os-insight-icon">!</div>
                                <div>
                                    <h3>ပြန်စစ်သင့်တဲ့အချက်များ</h3>
                                    <ul>
                                        @foreach($result['warnings'] as $warning)
                                            <li>{{ $warning }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @if(!empty($result['notes']))
                            <div class="pbr-os-insight note">
                                <div class="pbr-os-insight-icon">i</div>
                                <div>
                                    <h3>Business Note</h3>
                                    <ul>
                                        @foreach($result['notes'] as $note)
                                            <li>{{ $note }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @if($canManage && $activeSession)
                            <div class="pbr-os-approval-zone">
                                <div>
                                    <span>ဒီ Scenario ကိုဘာလုပ်မလဲ?</span>
                                    <h3>Draft နဲ့ Agreed Rule ကိုမရောပါဘူး</h3>
                                    <p>Workspace Output က working draft reference ဖြစ်ပါတယ်။ <b>Approve as Agreed Business Rule</b> လုပ်မှ နောက် Chapters နဲ့ AI Advisor က official current rule အဖြစ်ယူသုံးပါမယ်။</p>
                                </div>
                                <div class="pbr-os-approval-actions">
                                    <form method="POST" action="{{ route('workspaces.tools.scenarios.output', [$workspace, $tool->slug, $activeSession->id]) }}">
                                        @csrf
                                        <button class="pbr-os-btn secondary" type="submit">Create Draft Output</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspaces.tools.scenarios.approve', [$workspace, $tool->slug, $activeSession->id]) }}" data-confirm-agreed>
                                        @csrf
                                        <button class="pbr-os-btn approve" type="submit">✓ Approve as Agreed Business Rule</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </section>
                @elseif(!$canManage)
                    <section class="pbr-os-panel pbr-os-empty-state">
                        <div class="pbr-os-empty-icon">◎</div>
                        <h2>Owner/Admin က ဒီ Tool အတွက် Agreed Rule မသတ်မှတ်ရသေးပါ</h2>
                        <p>Draft scenarios တွေကို Partner account က မမြင်နိုင်ပါဘူး။ Agreed Business Rule ရှိလာရင် ဒီနေရာမှာ automatically ပေါ်လာပါမယ်။</p>
                    </section>
                @endif

                @if($latestAgreedOutput)
                    <section class="pbr-os-panel pbr-os-current-rule">
                        <div>
                            <span class="pbr-os-agreed-pill">✓ Current Agreed Business Rule</span>
                            <h2>Revision {{ $latestAgreedOutput->revision }}</h2>
                            <p>Approved {{ optional($latestAgreedOutput->agreed_at)->format('d M Y, H:i') }}</p>
                        </div>
                        <p>ဒီ revision ကို Chapter {{ $chapterNumber }} ရဲ့ connected operating data နဲ့ PBR AI Advisor context အတွက်အသုံးပြုနိုင်ပါတယ်။</p>
                    </section>
                @endif

                <div class="pbr-os-legal-note">
                    <strong>Important</strong>
                    <p>{{ config('pbr_operating_tools.shared_notes.planning_only_mm') }}</p>
                    <p>{{ config('pbr_operating_tools.shared_notes.agreement_mm') }}</p>
                </div>
            </main>
        </div>
    </div>
</section>
@endsection
