@php
    $isRecordTool =
        (bool) ($toolContract['is_record'] ?? false);

    $prefillSources =
        $toolContract['prefill_source_labels'] ?? [];

    $upstreamDomains =
        $toolContract['upstream_domain_labels'] ?? [];

    $advisorySources =
        $toolContract['advisory_source_labels'] ?? [];
@endphp

<section
    class="pbr-connected-runtime"
    data-pbr-runtime-contract="{{ $toolContract['mode'] ?? 'current_rule' }}"
>
    <div class="pbr-connected-runtime-main">
        <div class="pbr-runtime-mode">
            <span>REAL BUSINESS MODE</span>

            <strong>
                @if($isRecordTool)
                    Operating Record
                @else
                    Current Business Rule
                @endif
            </strong>

            <p>
                @if($isRecordTool)
                    Approved entries တွေကို history အဖြစ်
                    append လုပ်ပါတယ်။ အရင် record ကို
                    overwrite မလုပ်ပါဘူး။
                @else
                    Approved revision က ဒီ function ရဲ့
                    Current Rule ဖြစ်ပါတယ်။ Working Draft
                    က approve မလုပ်မချင်း current state
                    ကို မပြောင်းပါဘူး။
                @endif
            </p>
        </div>

        <div class="pbr-runtime-source">
            <span>CONNECTED DATA</span>

            @if(!empty($prefillSources))
                <strong>Approved Prefill Sources</strong>

                <div class="pbr-runtime-tags">
                    @foreach($prefillSources as $source)
                        <i>
                            {{ $source['name'] }}
                        </i>
                    @endforeach
                </div>
            @elseif(!empty($upstreamDomains))
                <strong>Upstream Business Areas</strong>

                <div class="pbr-runtime-tags">
                    @foreach($upstreamDomains as $source)
                        <i>
                            {{ $source['name'] }}
                        </i>
                    @endforeach
                </div>

                <small>
                    ဒီ tool မှာ automatic prefill မရှိနိုင်ပေမယ့်
                    ဒီ Business Area တွေနဲ့ semantic dependency ရှိပါတယ်။
                </small>
            @else
                <strong>Direct Business Input</strong>

                <small>
                    ဒီ function က approved upstream rule ကို
                    အလိုအလျောက် copy မလုပ်ပါဘူး။
                </small>
            @endif
        </div>

        @if(!empty($advisorySources))
            <div class="pbr-runtime-advisory">
                <span>ADVISORY ONLY</span>

                <div class="pbr-runtime-tags">
                    @foreach($advisorySources as $source)
                        <i>
                            {{ $source['name'] }}
                        </i>
                    @endforeach
                </div>

                <small>
                    Advisory estimate က Current Rule
                    အဖြစ် အလိုအလျောက်မပြောင်းပါဘူး။
                </small>
            </div>
        @endif
    </div>

    @if(
        $isRecordTool
        && isset($operatingRecords)
    )
        <div
            class="pbr-operating-record-history"
            data-pbr-operating-record-history
        >
            <div class="pbr-record-history-head">
                <div>
                    <span>APPROVED OPERATING HISTORY</span>
                    <strong>
                        {{ $operatingRecords->count() }}
                        Recent Records
                    </strong>
                </div>

                <small>
                    Workspace-specific · Approved only
                </small>
            </div>

            @forelse($operatingRecords as $record)
                <article class="pbr-operating-record">
                    <div class="pbr-operating-record-head">
                        <div>
                            <strong>
                                {{
                                    $record->title
                                    ?: str($record->record_type)
                                        ->replace('_', ' ')
                                        ->title()
                                }}
                            </strong>

                            <small>
                                {{
                                    $record->record_date
                                        ?->format('d M Y')
                                    ?? optional(
                                        $record->effective_at
                                    )->format(
                                        'd M Y, H:i'
                                    )
                                    ?? 'Approved Record'
                                }}
                            </small>
                        </div>

                        <span>
                            {{ $record->record_type }}
                        </span>
                    </div>

                    <div class="pbr-operating-record-values">
                        @foreach(
                            collect($record->data ?? [])
                                ->filter(
                                    fn ($value) =>
                                        is_scalar($value)
                                        || $value === null
                                )
                                ->take(6)
                            as $key => $value
                        )
                            <div>
                                <span>
                                    {{
                                        str((string) $key)
                                            ->replace('_', ' ')
                                            ->title()
                                    }}
                                </span>

                                <strong>
                                    {{
                                        is_bool($value)
                                            ? (
                                                $value
                                                    ? 'Yes'
                                                    : 'No'
                                            )
                                            : (
                                                $value === null
                                                    || $value === ''
                                                    ? '—'
                                                    : $value
                                            )
                                    }}
                                </strong>
                            </div>
                        @endforeach
                    </div>

                    @if(
                        collect($record->data ?? [])
                            ->contains(
                                fn ($value) => is_array($value)
                            )
                    )
                        <details>
                            <summary>
                                Structured Record Details
                            </summary>

                            <pre>{{
                                json_encode(
                                    $record->data,
                                    JSON_PRETTY_PRINT
                                    | JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                )
                            }}</pre>
                        </details>
                    @endif
                </article>
            @empty
                <div class="pbr-record-empty">
                    Approved history မရှိသေးပါ။
                    Working Record တစ်ခုကို review လုပ်ပြီး
                    approve လုပ်တဲ့အခါ ဒီနေရာမှာ
                    audit history အဖြစ်ပေါ်လာပါမယ်။
                </div>
            @endforelse
        </div>
    @endif
</section>
