@extends('layouts.student-portal')

@section('title', $definition['title_mm'] ?? $tool->title_en)

@section('content')
@php
    $currency = $workspace->currency_code ?? 'THB';
    $internalNumber = (int) ($tool->chapter?->chapter_number ?? ($definition['chapter'] ?? 0));
    $businessArea = config('pbr_business_operating_system.areas.'.$internalNumber, []);
    $areaNameMm = $businessArea['name_mm'] ?? 'Business Operations';
    $areaNameEn = $businessArea['name_en'] ?? 'Business Operations';
    $areaSlug = $businessArea['slug'] ?? 'operations';

    $isRecordTool =
        (bool) ($toolContract['is_record'] ?? false);

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

<section
    class="pbr-os-page pbr-premium-tool-page"
    data-pbr-premium-tool
    data-pbr-tool-chapter="{{ $internalNumber }}"
>
    <div class="portal-wrap pbr-os-wrap">
        @include(
            'workspaces.tools.partials.premium-tool-command-bar',
            [
                'premiumToolTitleMm' => $definition['title_mm'] ?? $tool->title_mm ?? $tool->title_en,
                'premiumToolTitleEn' => $tool->title_en,
                'premiumToolAreaMm' => $areaNameMm,
                'premiumToolAreaEn' => $areaNameEn,
                'premiumToolCanManage' => $canManage,
                'premiumToolHasDraft' => (bool) $activeSession,
                'premiumToolHasActiveRule' => (bool) $latestAgreedOutput,
                'premiumToolIsRecord' => $isRecordTool,
            ]
        )

        <nav class="pbr-os-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('workspaces.show', $workspace) }}">{{ $workspace->business_name ?: $workspace->name }}</a>
            <span>›</span>
            <a href="{{ route('workspaces.tools.index', $workspace) }}#system-{{ $areaSlug }}">{{ $areaNameEn }}</a>
            <span>›</span>
            <span>{{ $tool->title_en }}</span>
        </nav>

        <header class="pbr-os-hero">
            <div class="pbr-os-hero-copy">
                <div class="pbr-os-kickers">
                    <span class="pbr-os-chapter-pill">{{ $areaNameMm }}</span>
                    @if($latestAgreedOutput)
                        <span class="pbr-os-agreed-pill">
                            {{
                                $isRecordTool
                                    ? '✓ Approved Record ရှိသည်'
                                    : '✓ Active Rule ရှိသည်'
                            }}
                        </span>
                    @elseif($activeSession)
                        <span class="pbr-os-type-pill">Working Draft</span>
                    @else
                        <span class="pbr-os-type-pill">Business Setup</span>
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
                    <small>{{ $workspace->business_stage === 'new' ? 'New Partnership' : 'Existing Partnership' }}</small>
                    <small>{{ $currency }}</small>
                </div>
            </aside>
        </header>

        @if(session('status'))
            <div class="pbr-os-alert success">{{ session('status') }}</div>
        @endif

        <div class="pbr-premium-tool-intelligence">
            @include(
                'workspaces.tools.partials.connected-runtime',
                [
                    'toolContract' => $toolContract,
                    'operatingRecords' => $operatingRecords,
                ]
            )

            @include(
                'workspaces.tools.partials.business-guidance',
                [
                    'businessGuidance' => $businessGuidance,
                ]
            )
        </div>

        @unless($canManage)
            <div class="pbr-os-readonly-banner">
                <div>
                    <strong>Partner Read-Only View</strong>
                    <p>
                        Owner/Admin က အတည်ပြုထားတဲ့
                        <b>
                            {{
                                $isRecordTool
                                    ? 'Operating Records'
                                    : 'Active Business Rule'
                            }}
                        </b>
                        ကိုသာ မြင်ရပါတယ်။
                        Working Draft၊ private scenario နဲ့
                        owner-only input တွေကို မပြပါဘူး။
                    </p>
                </div>
                <span>Permission Safe</span>
            </div>
        @endunless

        <div class="pbr-os-layout {{ $canManage ? '' : 'readonly' }}">
            @if($canManage)
                <aside class="pbr-os-sidebar">
                    <div class="pbr-os-side-card">
                        <span class="pbr-os-side-label">OPERATING WORKFLOW</span>
                        <ol class="pbr-os-steps">
                            <li class="active"><span>1</span><div><b>Actual Data ထည့်ပါ</b><small>လက်ရှိ Business information</small></div></li>
                            <li><span>2</span><div><b>Review Result</b><small>Calculation, warning နဲ့ option ကိုစစ်ပါ</small></div></li>
                            <li><span>3</span><div><b>Working Draft သိမ်းပါ</b><small>Active Rule ကို မထိခိုက်ဘဲ ပြင်ဆင်ပါ</small></div></li>
                            <li>
                                <span>4</span>
                                <div>
                                    <b>
                                        {{
                                            $isRecordTool
                                                ? 'Approve & Record'
                                                : 'Approve & Activate'
                                        }}
                                    </b>
                                    <small>
                                        {{
                                            $isRecordTool
                                                ? 'Operating History ထဲကို approved entry အဖြစ်ထည့်မယ်'
                                                : 'Business မှာ အသုံးပြုမယ့် Current Rule ဖြစ်မယ်'
                                        }}
                                    </small>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <div class="pbr-os-side-card">
                        <div class="pbr-os-side-head">
                            <div>
                                <span class="pbr-os-side-label">WORKING PLANS</span>
                                <strong>{{ $drafts->count() }} Drafts</strong>
                            </div>
                            <a href="{{ route('workspaces.tools.operating.show', [$workspace, $tool->slug]) }}">New</a>
                        </div>

                        @forelse($drafts as $draft)
                            <a
                                class="pbr-os-draft-link {{ $activeSession?->id === $draft->id ? 'active' : '' }}"
                                href="{{ route('workspaces.tools.operating.show', [$workspace, $tool->slug, 'session' => $draft->id]) }}"
                            >
                                <span>{{ $draft->scenario_name ?: 'Untitled Working Plan' }}</span>
                                <small>{{ optional($draft->last_saved_at)->diffForHumans() }}</small>
                            </a>
                        @empty
                            <p class="pbr-os-empty-small">Working Draft မရှိသေးပါ။ Data ထည့်ပြီး လိုအပ်ရင် Draft သိမ်းနိုင်ပါတယ်။</p>
                        @endforelse
                    </div>

                    @if($outputHistory->isNotEmpty())
                        <div class="pbr-os-side-card">
                            <span class="pbr-os-side-label">
                                {{
                                    $isRecordTool
                                        ? 'APPROVAL HISTORY'
                                        : 'RULE HISTORY'
                                }}
                            </span>
                            <div class="pbr-os-history-list">
                                @foreach($outputHistory as $output)
                                    <div>
                                        <span class="{{ $output->status === 'agreed' ? 'agreed' : 'draft' }}">
                                            {{ $output->status === 'agreed' ? 'ACTIVE' : 'DRAFT' }}
                                        </span>
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
                    <section class="pbr-os-panel pbr-os-input-panel" id="tool-workspace">
                        <div class="pbr-os-panel-head">
                            <div>
                                <span class="portal-kicker">BUSINESS DATA</span>
                                <h2>{{ $activeSession ? 'Working Plan ကို ပြင်နေသည်' : 'လက်ရှိ Business အချက်အလက် ထည့်ပါ' }}</h2>
                                <p>ဒီ Operating Function အတွက် ဆုံးဖြတ်ချက်ချဖို့ လိုအပ်တဲ့ data ကိုပဲ ထည့်ပါ။ Save လုပ်ထားတဲ့ Draft က Active Rule ကို အလိုအလျောက် မပြောင်းပါဘူး။</p>
                            </div>
                            @if($activeSession)
                                <span class="pbr-os-session-badge">Working Draft #{{ $activeSession->id }}</span>
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

                            @include(
                                'workspaces.tools.partials.operating-context'
                            )

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
                                                <button type="button" class="pbr-os-add-row" data-repeater-add>+ Record ထည့်ရန်</button>
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
                                                        <button type="button" class="pbr-os-remove-row" data-repeater-remove aria-label="Remove record">×</button>
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
                                                    <button type="button" class="pbr-os-remove-row" data-repeater-remove aria-label="Remove record">×</button>
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
                                                        <span><b>{{ $itemLabel }}</b><small>လက်ရှိ Business မှာ သက်ဆိုင်ရင် ရွေးပါ</small></span>
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
                                <button type="submit" class="pbr-os-btn primary">Result စစ်ရန်</button>

                                <div class="pbr-os-save-cluster">
                                    <label for="scenario_name">Plan / Version အမည်</label>
                                    <input
                                        id="scenario_name"
                                        type="text"
                                        name="scenario_name"
                                        maxlength="120"
                                        value="{{ old('scenario_name', $activeSession?->scenario_name ?? '') }}"
                                        placeholder="ဥပမာ: Current Policy 2026 / Option A"
                                    >
                                    <button
                                        type="submit"
                                        class="pbr-os-btn secondary"
                                        formaction="{{ route('workspaces.tools.operating.save', [$workspace, $tool->slug]) }}"
                                    >Working Draft သိမ်းရန်</button>
                                </div>
                            </div>
                        </form>
                    </section>
                @endif

                @if($result)
                    <section class="pbr-os-panel pbr-os-result-panel" id="result">
                        <div class="pbr-os-result-hero">
                            <span>{{ $result['headline']['label'] ?? 'Business Result' }}</span>
                            <strong>{{ $formatValue($result['headline']['value'] ?? null, $result['headline']['format'] ?? 'text') }}</strong>
                            <small>လက်ရှိထည့်ထားသော Business Data အပေါ် အခြေခံထားသည်</small>
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
                                    <span>{{ count($table['rows'] ?? []) }} records</span>
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
                                    <h3>Action မလုပ်ခင် ပြန်စစ်သင့်တဲ့အချက်များ</h3>
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
                                    <h3>Business Notes</h3>
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
                                    <span>
                                        {{
                                            $isRecordTool
                                                ? 'Working Record ကို အတည်ပြုမလား?'
                                                : 'Working Draft ကို အတည်ပြုမလား?'
                                        }}
                                    </span>

                                    <h3>
                                        {{
                                            $isRecordTool
                                                ? 'Draft နဲ့ Approved Operating History ကို သီးခြားထားပါတယ်'
                                                : 'Draft နဲ့ Active Business Rule ကို သီးခြားထားပါတယ်'
                                        }}
                                    </h3>

                                    <p>
                                        @if($isRecordTool)
                                            Working Record ကို သိမ်းတာနဲ့
                                            Operating History မပြောင်းပါဘူး။
                                            <b>Approve & Record</b>
                                            လုပ်မှ approved history entry
                                            ဖြစ်ပြီး PBR AI Advisor က
                                            business context အဖြစ်
                                            အသုံးပြုနိုင်ပါတယ်။
                                        @else
                                            Draft ကို သိမ်းတာနဲ့
                                            လက်ရှိ Rule မပြောင်းပါဘူး။
                                            <b>Approve & Activate</b>
                                            လုပ်မှ ဒီ Business Area ရဲ့
                                            Current Rule ဖြစ်ပြီး connected
                                            operating data နဲ့ PBR AI Advisor
                                            က အသုံးပြုပါမယ်။
                                        @endif
                                    </p>
                                </div>
                                @if($approvalState)
                                    <div
                                        class="pbr-approval-readiness {{ $approvalState['ready'] ? 'ready' : 'blocked' }}"
                                        data-pbr-approval-ready="{{ $approvalState['ready'] ? '1' : '0' }}"
                                    >
                                        <div class="pbr-approval-readiness-head">
                                            <span>
                                                {{
                                                    $approvalState['ready']
                                                        ? 'READY FOR APPROVAL'
                                                        : 'APPROVAL BLOCKED'
                                                }}
                                            </span>

                                            <strong>
                                                {{
                                                    $approvalState['ready']
                                                        ? 'Business data ကို approve လုပ်နိုင်ပါပြီ'
                                                        : 'အောက်ကအချက်တွေကို အရင်ပြင်ပါ'
                                                }}
                                            </strong>
                                        </div>

                                        @if(!empty($approvalState['errors']))
                                            <ul class="pbr-approval-errors">
                                                @foreach($approvalState['errors'] as $approvalError)
                                                    <li>
                                                        {{ $approvalError }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if(!empty($approvalState['warnings']))
                                            <details class="pbr-approval-warnings">
                                                <summary>
                                                    Review Notes
                                                    ({{ count($approvalState['warnings']) }})
                                                </summary>

                                                <ul>
                                                    @foreach($approvalState['warnings'] as $approvalWarning)
                                                        <li>
                                                            {{ $approvalWarning }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </div>
                                @endif

                                <div class="pbr-os-approval-actions">
                                    <form method="POST" action="{{ route('workspaces.tools.scenarios.output', [$workspace, $tool->slug, $activeSession->id]) }}">
                                        @csrf
                                        <button class="pbr-os-btn secondary" type="submit">Review Output သိမ်းရန်</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspaces.tools.scenarios.approve', [$workspace, $tool->slug, $activeSession->id]) }}" data-confirm-agreed>
                                        @csrf
                                        <button
                                            class="pbr-os-btn approve"
                                            type="submit"
                                            @disabled(!($approvalState['ready'] ?? false))
                                        >
                                            {{
                                                $isRecordTool
                                                    ? '✓ Approve & Add to History'
                                                    : '✓ Approve & Activate Business Rule'
                                            }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </section>
                @elseif(!$canManage)
                    <section class="pbr-os-panel pbr-os-empty-state">
                        <div class="pbr-os-empty-icon">◎</div>
                        <h2>
                            {{
                                $isRecordTool
                                    ? 'ဒီ Operating Function အတွက် Approved Record မရှိသေးပါ'
                                    : 'ဒီ Operating Function အတွက် Active Business Rule မရှိသေးပါ'
                            }}
                        </h2>

                        <p>
                            Partner account မှာ Working Draft တွေကို
                            မပြပါဘူး။
                            Owner/Admin က
                            {{
                                $isRecordTool
                                    ? 'record'
                                    : 'rule'
                            }}
                            အတည်ပြုပြီးရင် ဒီနေရာမှာ
                            approved operating information
                            အဖြစ် ပေါ်လာပါမယ်။
                        </p>
                    </section>
                @endif

                @if($latestAgreedOutput)
                    <section class="pbr-os-panel pbr-os-current-rule">
                        <div>
                            <span class="pbr-os-agreed-pill">
                                {{
                                    $isRecordTool
                                        ? '✓ Latest Approved Operating Record'
                                        : '✓ Current Active Business Rule'
                                }}
                            </span>

                            <h2>
                                Revision
                                {{ $latestAgreedOutput->revision }}
                            </h2>

                            <p>
                                Approved
                                {{
                                    optional(
                                        $latestAgreedOutput->agreed_at
                                    )->format('d M Y, H:i')
                                }}
                            </p>
                        </div>

                        <p>
                            @if($isRecordTool)
                                ဒီ Revision က
                                <b>{{ $areaNameEn }}</b>
                                ရဲ့ latest approved record ဖြစ်ပါတယ်။
                                အရင် approved records တွေကို
                                Operating History ထဲမှာ ဆက်ထိန်းထားပြီး
                                permission-safe PBR AI context မှာ
                                အသုံးပြုနိုင်ပါတယ်။
                            @else
                                ဒီ Revision က
                                <b>{{ $areaNameEn }}</b>
                                ရဲ့ လက်ရှိအသုံးပြုနေသော Rule ဖြစ်ပြီး
                                connected business workflows နဲ့
                                permission-safe PBR AI context မှာ
                                အသုံးပြုနိုင်ပါတယ်။
                            @endif
                        </p>
                    </section>
                @endif

                @include(
                    'workspaces.tools.partials.operating-action-board'
                )

                <div class="pbr-os-legal-note">
                    <strong>Planning & Governance Note</strong>
                    <p>{{ config('pbr_business_operating_system.legal_note_mm') }}</p>
                    <p>{{ config('pbr_operating_tools.shared_notes.agreement_mm') }}</p>
                </div>
            </main>
        </div>
    </div>
</section>
@endsection
