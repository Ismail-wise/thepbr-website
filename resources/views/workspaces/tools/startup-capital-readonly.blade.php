@extends('layouts.student-portal')

@section('title', 'Startup Capital Planner')

@section('content')
<section class="pbr-tools-section">
    <div class="portal-wrap">
        <div class="pbr-tool-page-head">
            <div>
                <a href="{{ route('workspaces.tools.index', $workspace) }}" class="pbr-tools-back">← Back to 10-Chapter System</a>
                <span class="portal-kicker">Chapter 1 · Capital Contribution</span>
                <h1>Startup Capital Planner</h1>
                <p>Owner/Admin အတည်ပြုထားတဲ့ Startup Capital rule ကို Partner account က read-only ကြည့်နိုင်ပါတယ်။</p>
            </div>
            <div class="pbr-tool-context-box">
                <span>Current Business</span>
                <strong>{{ $workspace->business_name ?: $workspace->name }}</strong>
                <small>Currency: {{ $workspace->currency_code ?? 'THB' }}</small>
                @if($latestAgreedOutput)
                    <small style="color:#bbf0ce;">✓ Agreed · Revision {{ $latestAgreedOutput->revision }}</small>
                @endif
            </div>
        </div>

        <div class="pbr-os-readonly-banner">
            <div>
                <strong>Partner Read-Only View</strong>
                <p>Draft cost lists နဲ့ private calculation scenarios တွေကို မပြပါဘူး။ Agreed Business Rule အဖြစ် approve လုပ်ထားတဲ့ result ကိုပဲပြထားပါတယ်။</p>
            </div>
            <span>Permission Safe</span>
        </div>

        @if($result)
            <div class="pbr-calculator-layout">
                <div class="pbr-calculator-panel">
                    <span class="portal-kicker">Current Agreed Rule</span>
                    <h2 style="margin:8px 0;">Startup Capital Structure</h2>
                    <p style="color:#65777d;line-height:1.7;">ဒီ result က current Business ရဲ့ approved Startup Capital plan ဖြစ်ပါတယ်။</p>

                    <div class="pbr-breakdown" style="margin-top:18px;">
                        <h3>Category Breakdown</h3>
                        @forelse($result['categories'] ?? [] as $category)
                            <div class="pbr-breakdown-row">
                                <div>
                                    <span>{{ $category['name'] ?? 'Category' }}</span>
                                    <strong>{{ number_format((float) ($category['percentage'] ?? 0), 2) }}%</strong>
                                </div>
                                <div class="pbr-breakdown-track"><i style="width: {{ min(100, (float) ($category['percentage'] ?? 0)) }}%"></i></div>
                                <small>{{ $workspace->currency_code ?? 'THB' }} {{ number_format((float) ($category['subtotal'] ?? 0), 2) }}</small>
                            </div>
                        @empty
                            <p class="pbr-muted-copy">Category details မရှိသေးပါ။</p>
                        @endforelse
                    </div>
                </div>

                <aside class="pbr-calculator-results">
                    <span class="portal-kicker">Agreed Capital Summary</span>
                    <div class="pbr-total-result">
                        <span>Total Startup Capital</span>
                        <strong>{{ $workspace->currency_code ?? 'THB' }} {{ number_format((float) ($result['total_startup_capital'] ?? 0), 2) }}</strong>
                    </div>
                    <div class="pbr-result-stats">
                        <div><span>Categories</span><strong>{{ $result['category_count'] ?? count($result['categories'] ?? []) }}</strong></div>
                        <div><span>Items with Amount</span><strong>{{ $result['item_count'] ?? 0 }}</strong></div>
                    </div>
                    @if(!empty($result['largest_category']))
                        <div class="pbr-largest-cost">
                            <span>Largest Category</span>
                            <strong>{{ $result['largest_category']['name'] ?? '—' }}</strong>
                            <p>{{ $workspace->currency_code ?? 'THB' }} {{ number_format((float) ($result['largest_category']['subtotal'] ?? 0), 2) }}</p>
                        </div>
                    @endif
                </aside>
            </div>

            @if($latestAgreedOutput)
                <section class="pbr-os-panel pbr-os-current-rule" style="margin-top:18px;">
                    <div>
                        <span class="pbr-os-agreed-pill">✓ Current Agreed Business Rule</span>
                        <h2>Revision {{ $latestAgreedOutput->revision }}</h2>
                        <p>Approved {{ optional($latestAgreedOutput->agreed_at)->format('d M Y, H:i') }}</p>
                    </div>
                    <p>ဒီ approved result ကို Capital domain, downstream Chapters နဲ့ PBR AI Advisor က authorized context အဖြစ်အသုံးပြုနိုင်ပါတယ်။</p>
                </section>
            @endif
        @else
            <section class="pbr-os-panel pbr-os-empty-state">
                <div class="pbr-os-empty-icon">◎</div>
                <h2>Agreed Startup Capital Rule မရှိသေးပါ</h2>
                <p>Owner/Admin က Startup Capital scenario တစ်ခုကို Agreed Business Rule အဖြစ် approve လုပ်ပြီးနောက် ဒီနေရာမှာပေါ်လာပါမယ်။</p>
            </section>
        @endif

        <div class="pbr-os-legal-note" style="margin-top:18px;">
            <strong>Important</strong>
            <p>ဒီ Startup Capital result က planning information ဖြစ်ပြီး accounting, tax, financing သို့မဟုတ် legal advice ကို အစားမထိုးပါ။</p>
        </div>
    </div>
</section>
@endsection
