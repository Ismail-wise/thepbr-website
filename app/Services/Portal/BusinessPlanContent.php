<?php

namespace App\Services\Portal;

use App\Models\WorkspaceBusinessPlanRevision;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BusinessPlanContent
{
    public const FIELDS = [
        'business_name' => 'လုပ်ငန်းနာမည်',
        'business_stage' => 'လုပ်ငန်းအခြေအနေ',
        'value_proposition' => 'ဘာပေးမလဲ၊ ဘာကြောင့် ရွေးဝယ်မလဲ',
        'customer_segments' => 'ဘယ်သူတွေကို ရောင်းမလဲ',
        'channels' => 'ဘယ်နေရာကနေ ရောင်းမလဲ',
        'customer_relationships' => 'Customer တွေနဲ့ ဘယ်လို ဆက်ဆံထိန်းသိမ်းမလဲ',
        'revenue_streams' => 'ဝင်ငွေ ဘယ်လိုရမလဲ',
        'key_resources' => 'ဘာပစ္စည်း၊ လူနဲ့ အရင်းအမြစ်တွေ လိုမလဲ',
        'key_activities' => 'အဓိက ဘာအလုပ်တွေ လုပ်ရမလဲ',
        'key_partners' => 'ဘယ်သူတွေနဲ့ ပူးပေါင်းမလဲ',
        'cost_structure' => 'အဓိက ကုန်ကျစရိတ်တွေက ဘာတွေလဲ',
        'demand_notes' => 'ဝယ်လိုအားအတွက် ဘာအထောက်အထား ရှိထားလဲ',
        'growth_notes' => 'လုပ်ငန်းကို ဘယ်လို တိုးချဲ့မလဲ',
        'break_even_notes' => 'အရှုံးအမြတ် ညီမျှမယ့် အခြေအနေအတွက် မှတ်စု',
    ];

    public const STAGES = ['planning' => 'စတင်ရန် ပြင်ဆင်နေဆဲ', 'operating' => 'လက်ရှိ လည်ပတ်နေဆဲ'];

    public const GROUPS = [
        'လုပ်ငန်းအခြေခံ' => ['value_proposition', 'customer_segments'],
        'Customer နှင့် ဝင်ငွေ' => ['channels', 'customer_relationships', 'revenue_streams'],
        'လုပ်ငန်းလည်ပတ်မှု' => ['key_resources', 'key_activities', 'key_partners', 'cost_structure'],
        'ဝယ်လိုအားနှင့် တိုးချဲ့မှု' => ['demand_notes', 'growth_notes', 'break_even_notes'],
    ];

    public function rules(): array
    {
        $rules = array_fill_keys(array_keys(self::FIELDS), ['nullable', 'string', 'max:3000']);
        $rules['business_name'] = ['nullable', 'string', 'max:160'];
        $rules['business_stage'] = ['nullable', 'string', Rule::in(array_keys(self::STAGES))];

        return $rules;
    }

    public function normalize(array $input): array
    {
        $content = [];
        foreach (self::FIELDS as $field => $label) {
            $content[$field] = trim((string) ($input[$field] ?? ''));
        }

        return $content;
    }

    public function missing(array $content): array
    {
        return array_filter(
            array_intersect_key(self::FIELDS, array_flip(['business_name', 'value_proposition', 'customer_segments'])),
            fn (string $label, string $field): bool => trim((string) ($content[$field] ?? '')) === '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function validateConfirmation(array $content): void
    {
        Validator::make($content, [
            'business_name' => ['required', 'string', 'max:160'],
            'value_proposition' => ['required', 'string', 'max:3000'],
            'customer_segments' => ['required', 'string', 'max:3000'],
        ], [], self::FIELDS)->validate();
    }

    public function knowledgeText(WorkspaceBusinessPlanRevision $revision): string
    {
        $lines = ['Business Plan · Version '.$revision->revision, 'Owner-confirmed record: '.$revision->created_at->toIso8601String()];
        foreach (self::FIELDS as $field => $label) {
            $value = $revision->content[$field] ?? '';
            if ($value === '') {
                continue;
            }
            if ($field === 'business_stage') {
                $value = self::STAGES[$value] ?? $value;
            }
            $lines[] = $label.': '.$value;
        }

        return implode("\n\n", $lines);
    }
}
