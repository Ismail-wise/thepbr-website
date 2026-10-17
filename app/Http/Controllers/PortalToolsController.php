<?php

namespace App\Http\Controllers;

use App\Models\PortalSavedItem;
use App\Services\Portal\PartnershipCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalToolsController extends Controller
{
    public const TOPICS = [
        'ရည်ရွယ်ချက်နှင့် ရေရှည်ဦးတည်ချက်', 'တစ်ဦးချင်း ထည့်ဝင်မှုနှင့် အချိန်သတ်မှတ်ချက်',
        'Partner တစ်ဦးချင်း ပေးနိုင်သော အလုပ်ချိန်', 'တာဝန်နှင့် အစီရင်ခံမည့်ပုံစံ',
        'လစာ၊ ဝန်ဆောင်ခနှင့် အမြတ်ဝေစု', 'အမြတ်၊ အရှုံး ခွဲဝေမှု',
        'ဘဏ်ဝင်ခွင့်နှင့် ငွေသုံးစွဲခွင့်', 'ဆုံးဖြတ်ခွင့်နှင့် အတူသဘောတူရမည့်ကိစ္စ',
        'ငွေစာရင်းနှင့် လုပ်ငန်းအချက်အလက် မျှဝေမှု', 'သဘောထားကွဲလွဲမှု ဖြေရှင်းပုံ',
        'Partner အသစ်ဝင်ခြင်း၊ ထွက်ခြင်းနှင့် လွှဲပြောင်းခြင်း', 'သဘောတူထားသည်များ ပြန်လည်သုံးသပ်မည့်ရက်',
    ];

    public function show(Request $request, string $tool): View
    {
        abort_unless(in_array($tool, ['capital', 'shares', 'checklist'], true), 404);
        $previous = PortalSavedItem::query()->where('user_id', $request->user()->id)->where('kind', $tool)->latest('id')->first();

        return view('portal.tool', ['tool' => $tool, 'previous' => $previous, 'topics' => self::TOPICS]);
    }

    public function save(Request $request, string $tool, PartnershipCalculator $calculator): RedirectResponse
    {
        if ($tool === 'capital') {
            $input = $request->validate([
                'pre' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000000000'],
                'assets' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000000000'],
                'monthly' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000000000'],
                'months' => ['required', 'integer', 'between:0,120'],
                'reserve' => ['required', 'numeric', 'between:0,100'],
                'available' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000000000'],
                'currency' => ['required', 'in:THB,MMK,USD'],
            ]);
            $output = $calculator->capital($input);
            $title = 'Capital Calculator';
        } elseif ($tool === 'shares') {
            $input = $request->validate([
                'unit' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,1000000000'],
                'currency' => ['required', 'in:THB,MMK,USD'],
                'partners' => ['required', 'array', 'min:2', 'max:10'],
                'partners.*.name' => ['required', 'string', 'max:100'],
                'partners.*.value' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000000000'],
            ]);
            $output = $calculator->shares($input);
            $title = 'Share Simulator';
        } elseif ($tool === 'checklist') {
            $input = $request->validate([
                'checks' => ['required', 'array', 'size:12'],
                'checks.*.done' => ['required', 'boolean'],
                'checks.*.note' => ['nullable', 'string', 'max:2000'],
            ]);
            // Normalize indexes; all topic labels come from the server.
            $checks = array_values($input['checks']);
            $output = ['items' => []];
            foreach (self::TOPICS as $i => $topic) {
                $output['items'][] = ['topic' => $topic, 'done' => (bool) $checks[$i]['done'], 'note' => $checks[$i]['note'] ?? ''];
            }
            $input = ['checks' => $checks];
            $title = 'Partnership Checklist';
        } else {
            abort(404);
        }

        $item = PortalSavedItem::create(['user_id' => $request->user()->id, 'kind' => $tool, 'title' => $title, 'input' => $input, 'output' => $output]);

        return redirect()->route('portal.saved', $item)->with('success', 'ရလဒ်ကို သင့် Account ထဲမှာ သိမ်းပြီးပါပြီ။');
    }
}
