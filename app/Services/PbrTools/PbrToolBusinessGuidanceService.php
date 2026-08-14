<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;

class PbrToolBusinessGuidanceService
{
    public function build(
        ChapterTool $tool,
        array $definition,
        ?array $result,
        array $runtimeContract,
        ?array $approvalState,
        bool $hasApprovedRule,
        bool $hasWorkingDraft
    ): array {
        $domain = (string) (
            $runtimeContract['domain'] ?? ''
        );

        $isRecord = (bool) (
            $runtimeContract['is_record'] ?? false
        );

        $headline = is_array(
            $result['headline'] ?? null
        )
            ? $result['headline']
            : null;

        $warnings = is_array(
            $result['warnings'] ?? null
        )
            ? array_values(
                array_filter(
                    $result['warnings'],
                    'is_string'
                )
            )
            : [];

        $notes = is_array(
            $result['notes'] ?? null
        )
            ? array_values(
                array_filter(
                    $result['notes'],
                    'is_string'
                )
            )
            : [];

        [$statusKey, $statusLabel, $nextAction] =
            $this->status(
                $result,
                $approvalState,
                $hasApprovedRule,
                $hasWorkingDraft,
                $isRecord
            );

        $downstream =
            $this->downstreamDomains($domain);

        $sourceLabels =
            collect(
                $runtimeContract[
                    'prefill_source_labels'
                ] ?? []
            )
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

        $advisoryLabels =
            collect(
                $runtimeContract[
                    'advisory_source_labels'
                ] ?? []
            )
                ->pluck('name')
                ->filter()
                ->values()
                ->all();

        return [
            'domain' => $domain,
            'status_key' => $statusKey,
            'status_label' => $statusLabel,
            'purpose_mm' =>
                $definition['purpose_mm']
                ?? $tool->description
                ?? '',
            'next_action_mm' => $nextAction,
            'headline' => $headline,
            'warning_count' => count($warnings),
            'note_count' => count($notes),
            'source_names' => $sourceLabels,
            'advisory_names' => $advisoryLabels,
            'downstream_domains' => $downstream,
            'business_questions' =>
                $this->businessQuestions($domain),
            'approval_effect_mm' =>
                $isRecord
                    ? 'Approve လုပ်ရင် ဒီ entry ကို Operating History ထဲမှာ record အသစ်အဖြစ် ထည့်မယ်။ အရင် record ကို overwrite မလုပ်ပါဘူး။'
                    : 'Approve လုပ်ရင် ဒီ function ရဲ့ Current Business Rule revision အသစ်ဖြစ်မယ်။ Working Draft သာရှိနေချိန်မှာ current state မပြောင်းပါဘူး။',
            'connection_effect_mm' =>
                $this->connectionEffect(
                    $isRecord,
                    $downstream
                ),
            'guardrails' =>
                $this->guardrails(
                    $isRecord,
                    $advisoryLabels
                ),
        ];
    }

    private function status(
        ?array $result,
        ?array $approvalState,
        bool $hasApprovedRule,
        bool $hasWorkingDraft,
        bool $isRecord
    ): array {
        if (
            $approvalState !== null
            && ! ($approvalState['ready'] ?? false)
        ) {
            return [
                'blocked',
                'Approval Blocked',
                'Approval blockers တွေကို ဖြေရှင်းပြီး calculation / business data ကို ပြန်စစ်ပါ။',
            ];
        }

        if (
            $approvalState !== null
            && ($approvalState['ready'] ?? false)
        ) {
            return [
                'ready',
                'Ready for Approval',
                $isRecord
                    ? 'Result ကို review လုပ်ပြီး မှန်ကန်ရင် Approve & Add to History လုပ်ပါ။'
                    : 'Result ကို review လုပ်ပြီး မှန်ကန်ရင် Approve & Activate လုပ်ပါ။',
            ];
        }

        if ($hasWorkingDraft) {
            return [
                'working',
                'Working Change',
                'Working Draft ကို review လုပ်ပြီး approval readiness ပြည့်မပြည့် စစ်ပါ။',
            ];
        }

        if ($hasApprovedRule) {
            return [
                'active',
                $isRecord
                    ? 'Approved Operating History'
                    : 'Current Approved Rule',
                $isRecord
                    ? 'Approved history ကို လက်ရှိ operations နဲ့တိုက်စစ်ပြီး လိုအပ်မှ record အသစ်ထည့်ပါ။'
                    : 'Current Rule ကို ဆက်သုံးနိုင်ပါတယ်။ Business condition ပြောင်းမှ Working Change အသစ်ဖန်တီးပါ။',
            ];
        }

        if ($result !== null) {
            return [
                'calculated',
                'Calculated — Not Approved',
                'Result ကို business reality နဲ့စစ်ပြီး Working Draft အဖြစ်သိမ်းပါ။',
            ];
        }

        return [
            'setup',
            'Business Setup Required',
            'Default/demo values မဟုတ်ဘဲ လက်ရှိ business-specific data ကို အရင်ထည့်ပါ။',
        ];
    }

    private function downstreamDomains(
        string $domain
    ): array {
        if ($domain === '') {
            return [];
        }

        $domains = config(
            'pbr_canonical_data.domains',
            []
        );

        if (! is_array($domains)) {
            return [];
        }

        $result = [];

        foreach ($domains as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $readsFrom =
                $definition['reads_from'] ?? [];

            if (
                ! is_array($readsFrom)
                || ! in_array(
                    $domain,
                    $readsFrom,
                    true
                )
            ) {
                continue;
            }

            $result[] = [
                'key' => (string) $key,
                'name' =>
                    $definition['name']
                    ?? str((string) $key)
                        ->replace('_', ' ')
                        ->title()
                        ->toString(),
            ];
        }

        return $result;
    }

    private function businessQuestions(
        string $domain
    ): array {
        return match ($domain) {
            'capital' => [
                'ဒီ capital position က business ကို လုံလောက်တဲ့ operating runway ပေးနိုင်သလား?',
                'Funding gap / allocation ပြောင်းလဲရင် ဘယ်သူက ဘယ်အချိန် action ယူမလဲ?',
            ],

            'ownership' => [
                'Ownership units နဲ့ Voting Power ကို သီးခြားမှန်ကန်စွာ သတ်မှတ်ထားသလား?',
                'Partner အသစ်ဝင်ခြင်း သို့မဟုတ် dilution ဖြစ်ရင် rule ဘယ်လိုပြောင်းမလဲ?',
            ],

            'contribution' => [
                'Partner တစ်ယောက်ချင်းစီရဲ့ role, time နဲ့ contribution expectation ကို အတိအကျသိကြသလား?',
                'Contribution မပြည့်တဲ့အခါ ဘယ် operating response ကိုသုံးမလဲ?',
            ],

            'distribution' => [
                'Salary, Profit Share, Reserve နဲ့ Ownership ကို တစ်ခုတည်းလို့ မယူဘဲ သီးခြားထားသလား?',
                'Cash မလုံလောက်တဲ့ period မှာ distribution rule ဘယ်လိုပြောင်းမလဲ?',
            ],

            'financial_controls' => [
                'ဘယ် amount မှာ ဘယ်သူ approve လုပ်နိုင်တယ်ဆိုတာ ambiguity မရှိဘူးလား?',
                'Cash, budget, banking နဲ့ payment controls တွေကို တစ်ယောက်တည်းပေါ် မမှီစေဘူးလား?',
            ],

            'governance' => [
                'ဘယ် decision ကို ဘယ်သူဆုံးဖြတ်မလဲ၊ voting threshold ဘယ်လောက်လဲဆိုတာ ရှင်းလား?',
                'Deadlock ဖြစ်ရင် predetermined fallback path ရှိသလား?',
            ],

            'exit' => [
                'Partner တစ်ယောက်ထွက်ရင် value, notice, settlement နဲ့ handover sequence ရှင်းလား?',
                'Exit တစ်ခုကြောင့် business operations ရပ်တန့်နိုင်တဲ့ dependency ရှိသလား?',
            ],

            'continuity' => [
                'Key person တစ်ယောက် unavailable ဖြစ်ရင် backup authority နဲ့ access ရှိသလား?',
                'Succession, emergency authority နဲ့ ownership transition ကို ကြိုသတ်မှတ်ထားသလား?',
            ],

            'share_transfer' => [
                'Share transfer မလုပ်ခင် approval / first-offer / valuation rules ပြည့်စုံသလား?',
                'Proposed transfer ကို executed ownership change နဲ့ မရောထားဘူးလား?',
            ],

            'dispute_resolution' => [
                'Issue facts, owner, next action နဲ့ escalation deadline ရှင်းလား?',
                'Internal resolution မရရင် mediation / professional route ကို ဘယ်အချိန်သွားမလဲ?',
            ],

            default => [
                'ဒီ result ကို လက်ရှိ business operation မှာ ဘယ် decision အတွက်သုံးမလဲ?',
                'Approve မလုပ်ခင် ဘယ် assumption ကို partner တွေနဲ့ confirm လုပ်ဖို့လိုသလဲ?',
            ],
        };
    }

    private function connectionEffect(
        bool $isRecord,
        array $downstream
    ): string {
        if ($isRecord) {
            return 'Approved record ကို workspace Operating History နဲ့ permission-safe PBR AI context မှာ အသုံးပြုနိုင်ပါတယ်။';
        }

        if ($downstream === []) {
            return 'Approved state ကို ဒီ Business Area ရဲ့ authoritative current rule အဖြစ် သိမ်းပြီး permission-safe PBR AI context မှာ အသုံးပြုနိုင်ပါတယ်။';
        }

        $names = collect($downstream)
            ->pluck('name')
            ->implode(', ');

        return
            'Approve လုပ်ပြီးနောက် canonical dependency contracts ခွင့်ပြုထားတဲ့ connected areas ('
            .$names
            .') က ဒီ domain ရဲ့ approved current state ကိုသာ read လုပ်နိုင်ပါတယ်။';
    }

    private function guardrails(
        bool $isRecord,
        array $advisoryLabels
    ): array {
        $guards = [
            'Working Draft / scenario က Current Rule မဟုတ်ပါ။',
            'Connected tools တွေက approved canonical data ကိုသာ source of truth အဖြစ်ယူရပါမယ်။',
        ];

        if ($isRecord) {
            $guards[] =
                'Operating Record အသစ်က history ကို append လုပ်တာဖြစ်ပြီး previous record ကို silently replace မလုပ်ရပါ။';
        }

        if ($advisoryLabels !== []) {
            $guards[] =
                implode(', ', $advisoryLabels)
                .' က advisory source သာဖြစ်ပြီး certified/current business fact မဟုတ်ပါ။';
        }

        return $guards;
    }
}
