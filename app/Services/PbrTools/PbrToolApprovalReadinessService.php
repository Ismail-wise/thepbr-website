<?php

namespace App\Services\PbrTools;

use App\Models\ChapterTool;
use App\Models\PartnershipWorkspace;
use App\Models\ToolSession;
use Illuminate\Validation\ValidationException;

class PbrToolApprovalReadinessService
{
    private array $policyErrors = [];

    public function __construct(
        private readonly PbrOperatingToolEngine $engine
    ) {
    }

    public function assess(
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        ToolSession $session
    ): array {
        $tool->loadMissing(
            'chapter:id,chapter_number'
        );

        $chapterNumber = (int) (
            $tool->chapter?->chapter_number ?? 0
        );

        /*
         * Chapter 1 already has its dedicated Capital workflow,
         * validators and production UX. This service guarantees a
         * common contract for all 64 tools while adding the strict
         * generic approval gate to Chapters 2-10.
         */
        if ($chapterNumber === 1) {
            return [
                'ready' => true,
                'errors' => [],
                'warnings' => [],
                'tool_key' => $tool->tool_key,
                'chapter' => 1,
            ];
        }

        $definition =
            $this->engine->definition(
                $tool->tool_key
            );

        $input = is_array($session->input_data)
            ? $session->input_data
            : [];

        $defaults =
            $this->engine->defaultInput(
                $tool->tool_key
            );

        $handler = (string) (
            $definition['handler'] ?? ''
        );

        $errors = [];
        $warnings = [];

        /*
         * Drafts may be incomplete. Approval may not simply accept
         * untouched demonstration/default data.
         *
         * Checklist tools are an exception: "nothing implemented yet"
         * is itself a legitimate current readiness state.
         */
        if (
            $handler !== 'checklist'
            && ! $this->hasBusinessSpecificInput(
                $input,
                $defaults
            )
        ) {
            $errors[] =
                'Demo/default values ကို business-specific data မထည့်ဘဲ Current Rule အဖြစ် approve မလုပ်နိုင်ပါ။';
        }

        $this->policyErrors = [];

        $this->applyToolPolicies(
            $tool->tool_key,
            $input,
            $errors,
            $warnings
        );

        if ($this->policyErrors !== []) {
            $errors = array_merge(
                $errors,
                $this->policyErrors
            );
        }

        if (
            filled($definition['record_type'] ?? null)
        ) {
            $this->validateRecord(
                $tool->tool_key,
                $input,
                $errors
            );
        }

        if ($handler === 'checklist') {
            $checks = is_array(
                $input['checks'] ?? null
            )
                ? $input['checks']
                : [];

            if (
                ! collect($checks)
                    ->contains(
                        fn ($value) => (bool) $value
                    )
            ) {
                $warnings[] =
                    'Checklist item တစ်ခုမှ complete မဖြစ်သေးပါ။ ဒီ state ကို approve လုပ်ရင် 0% readiness ကို current assessment အဖြစ်မှတ်တမ်းတင်မှာဖြစ်ပါတယ်။';
            }
        }

        $resultWarnings = is_array(
            $session->result_data['warnings']
                ?? null
        )
            ? $session->result_data['warnings']
            : [];

        foreach ($resultWarnings as $warning) {
            if (
                is_string($warning)
                && trim($warning) !== ''
            ) {
                $warnings[] = trim($warning);
            }
        }

        $errors = array_values(array_unique(
            array_filter($errors)
        ));

        $warnings = array_values(array_unique(
            array_filter($warnings)
        ));

        return [
            'ready' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'tool_key' => $tool->tool_key,
            'chapter' => $chapterNumber,
        ];
    }

    public function assertReady(
        PartnershipWorkspace $workspace,
        ChapterTool $tool,
        ToolSession $session
    ): void {
        $state = $this->assess(
            $workspace,
            $tool,
            $session
        );

        if ($state['ready']) {
            return;
        }

        throw ValidationException::withMessages([
            'approval' =>
                'ဒီ Working Plan ကို approve မလုပ်နိုင်သေးပါ။ '
                .implode(' ', $state['errors']),
        ]);
    }

    private function applyToolPolicies(
        string $toolKey,
        array $input,
        array &$errors,
        array &$warnings
    ): void {
        switch ($toolKey) {
            case 'equity_split_simulator':
                $partners =
                    $this->rows(
                        $input,
                        'partners'
                    );

                $this->requireNamedRows(
                    $partners,
                    'name',
                    2,
                    'Equity comparison'
                );

                $weightTotal =
                    $this->number(
                        $input,
                        'capital_weight'
                    )
                    + $this->number(
                        $input,
                        'work_weight'
                    )
                    + $this->number(
                        $input,
                        'expertise_weight'
                    )
                    + $this->number(
                        $input,
                        'risk_weight'
                    );

                if (
                    abs($weightTotal - 100) > 0.01
                ) {
                    $errors[] =
                        'Equity Split weights စုစုပေါင်း 100% ဖြစ်ရပါမယ်။';
                }

                break;

            case 'cap_table_builder':
                $partners =
                    $this->rows(
                        $input,
                        'partners'
                    );

                $this->requireNamedRows(
                    $partners,
                    'name',
                    2,
                    'Cap Table'
                );

                $issuedUnits =
                    array_sum(
                        array_map(
                            fn (array $row): float =>
                                $this->number(
                                    $row,
                                    'units'
                                ),
                            $partners
                        )
                    );

                if ($issuedUnits <= 0) {
                    $errors[] =
                        'Cap Table approve လုပ်ဖို့ issued ownership units 0 ထက်ကြီးရပါမယ်။';
                }

                break;

            case 'voting_power_calculator':
                $partners =
                    $this->rows(
                        $input,
                        'partners'
                    );

                $this->requireNamedRows(
                    $partners,
                    'name',
                    2,
                    'Voting structure'
                );

                $totalVotes =
                    array_sum(
                        array_map(
                            fn (array $row): float =>
                                $this->number(
                                    $row,
                                    'voting_units'
                                ),
                            $partners
                        )
                    );

                if ($totalVotes <= 0) {
                    $errors[] =
                        'Voting structure approve လုပ်ဖို့ Voting Units လိုအပ်ပါတယ်။';
                }

                break;

            case 'share_value_calculator':
                if (
                    $this->number(
                        $input,
                        'equity_value'
                    ) <= 0
                ) {
                    $errors[] =
                        'Share value တွက်ဖို့ Business Equity Value 0 ထက်ကြီးရပါမယ်။';
                }

                if (
                    $this->number(
                        $input,
                        'total_units'
                    ) <= 0
                ) {
                    $errors[] =
                        'Total Ownership Units 0 ထက်ကြီးရပါမယ်။';
                }

                break;

            case 'vesting_calculator':
                $vesting =
                    $this->number(
                        $input,
                        'vesting_months'
                    );

                $cliff =
                    $this->number(
                        $input,
                        'cliff_months'
                    );

                if ($cliff > $vesting) {
                    $errors[] =
                        'Cliff period က full vesting period ထက် မရှည်ရပါဘူး။';
                }

                break;

            case 'role_responsibility_matrix':
            case 'partner_role_matrix':
                $this->requireRows(
                    $input,
                    'responsibilities',
                    1,
                    'Role / Responsibility'
                );

                break;

            case 'profit_distribution_calculator':
            case 'loss_sharing_simulator':
                $partners =
                    $this->rows(
                        $input,
                        'partners'
                    );

                $this->requireNamedRows(
                    $partners,
                    'name',
                    2,
                    'Distribution'
                );

                $percentageTotal =
                    array_sum(
                        array_map(
                            fn (array $row): float =>
                                $this->number(
                                    $row,
                                    'percentage'
                                ),
                            $partners
                        )
                    );

                if (
                    abs($percentageTotal - 100)
                    > 0.01
                ) {
                    $errors[] =
                        'Partner percentages စုစုပေါင်း 100% ဖြစ်ရပါမယ်။';
                }

                break;

            case 'salary_profit_share_planner':
                $partners =
                    $this->rows(
                        $input,
                        'partners'
                    );

                $this->requireNamedRows(
                    $partners,
                    'name',
                    2,
                    'Partner compensation'
                );

                $profitShareTotal =
                    array_sum(
                        array_map(
                            fn (array $row): float =>
                                $this->number(
                                    $row,
                                    'profit_share'
                                ),
                            $partners
                        )
                    );

                if (
                    $profitShareTotal > 100.01
                ) {
                    $errors[] =
                        'Profit Share စုစုပေါင်း 100% ထက် မကျော်ရပါဘူး။';
                }

                break;

            case 'reserve_fund_planner':
                if (
                    $this->number(
                        $input,
                        'target_months'
                    ) > 0
                    && $this->number(
                        $input,
                        'monthly_operating_cost'
                    ) <= 0
                ) {
                    $errors[] =
                        'Reserve target သတ်မှတ်ထားရင် Monthly Operating Cost လိုအပ်ပါတယ်။';
                }

                break;

            case 'expense_approval_matrix':
            case 'large_payment_approval_rules':
                $this->validateAmountRanges(
                    $input,
                    'rules',
                    $errors,
                    $warnings
                );

                break;

            case 'bank_authority_matrix':
                $this->requireRows(
                    $input,
                    'authorities',
                    1,
                    'Bank Authority'
                );

                break;

            case 'decision_rights_matrix':
                $this->requireRows(
                    $input,
                    'decisions',
                    1,
                    'Decision Rights'
                );

                break;

            case 'authority_level_builder':
                $this->requireRows(
                    $input,
                    'levels',
                    1,
                    'Authority Level'
                );

                break;

            case 'voting_simulator':
                $votes =
                    $this->rows(
                        $input,
                        'votes'
                    );

                $this->requireNamedRows(
                    $votes,
                    'name',
                    2,
                    'Voting simulation'
                );

                $weight =
                    array_sum(
                        array_map(
                            fn (array $row): float =>
                                $this->number(
                                    $row,
                                    'weight'
                                ),
                            $votes
                        )
                    );

                if ($weight <= 0) {
                    $errors[] =
                        'Voting simulation မှာ voting weight လိုအပ်ပါတယ်။';
                }

                break;

            case 'deadlock_detector':
            case 'deadlock_decision_tool':
                $voteTotal =
                    $this->number(
                        $input,
                        'yes_weight'
                    )
                    + $this->number(
                        $input,
                        'no_weight'
                    )
                    + $this->number(
                        $input,
                        'abstain_weight'
                    );

                if ($voteTotal <= 0) {
                    $errors[] =
                        'Deadlock analysis approve လုပ်ဖို့ actual voting weights လိုအပ်ပါတယ်။';
                }

                break;

            case 'partner_buyout_calculator':
                if (
                    $this->number(
                        $input,
                        'business_value'
                    ) <= 0
                ) {
                    $errors[] =
                        'Buyout planning အတွက် Business Equity Value လိုအပ်ပါတယ်။';
                }

                if (
                    $this->number(
                        $input,
                        'ownership_percentage'
                    ) <= 0
                ) {
                    $errors[] =
                        'Exiting ownership percentage 0 ထက်ကြီးရပါမယ်။';
                }

                break;

            case 'exit_value_simulator':
                $conservative =
                    $this->number(
                        $input,
                        'conservative_value'
                    );

                $base =
                    $this->number(
                        $input,
                        'base_value'
                    );

                $optimistic =
                    $this->number(
                        $input,
                        'optimistic_value'
                    );

                if (
                    $conservative <= 0
                    || $base <= 0
                    || $optimistic <= 0
                ) {
                    $errors[] =
                        'Exit scenarios အတွက် Conservative, Base နဲ့ Optimistic values သုံးခုလုံးလိုအပ်ပါတယ်။';
                }

                if (
                    $conservative > $base
                    || $base > $optimistic
                ) {
                    $errors[] =
                        'Exit values က Conservative ≤ Base ≤ Optimistic အစဉ်ဖြစ်ရပါမယ်။';
                }

                break;

            case 'notice_period_planner':
                if (
                    $this->text(
                        $input,
                        'notice_date'
                    ) === ''
                ) {
                    $errors[] =
                        'Exit Notice Date လိုအပ်ပါတယ်။';
                }

                if (
                    $this->number(
                        $input,
                        'handover_days'
                    )
                    > $this->number(
                        $input,
                        'notice_days'
                    )
                ) {
                    $warnings[] =
                        'Handover period က notice period ထက်ရှည်နေပါတယ်။ Practical timeline ကို review လုပ်ပါ။';
                }

                break;

            case 'exit_timeline':
            case 'escalation_timeline':
                if (
                    $this->text(
                        $input,
                        'start_date'
                    ) === ''
                ) {
                    $errors[] =
                        'Timeline approve လုပ်ဖို့ Start Date လိုအပ်ပါတယ်။';
                }

                break;

            case 'business_continuity_planner':
                $this->requireRows(
                    $input,
                    'functions',
                    1,
                    'Business Continuity'
                );

                break;

            case 'key_person_dependency_map':
                $this->requireRows(
                    $input,
                    'dependencies',
                    1,
                    'Key Person Dependency'
                );

                break;

            case 'succession_planner':
                $this->requireRows(
                    $input,
                    'roles',
                    1,
                    'Succession'
                );

                break;

            case 'emergency_authority_planner':
                if (
                    $this->text(
                        $input,
                        'trigger'
                    ) === ''
                ) {
                    $errors[] =
                        'Emergency Authority အတွက် trigger condition သတ်မှတ်ပါ။';
                }

                if (
                    $this->text(
                        $input,
                        'acting_role'
                    ) === ''
                ) {
                    $errors[] =
                        'Emergency ဖြစ်ရင် authority ယူမယ့် person/role လိုအပ်ပါတယ်။';
                }

                break;

            case 'ownership_transition_simulator':
                if (
                    $this->number(
                        $input,
                        'business_value'
                    ) <= 0
                ) {
                    $errors[] =
                        'Ownership transition planning အတွက် Business Equity Value လိုအပ်ပါတယ်။';
                }

                if (
                    $this->number(
                        $input,
                        'ownership_percentage'
                    ) <= 0
                ) {
                    $errors[] =
                        'Affected Ownership percentage 0 ထက်ကြီးရပါမယ်။';
                }

                break;

            case 'share_transfer_simulator':
                $seller =
                    mb_strtolower(
                        $this->text(
                            $input,
                            'seller_name'
                        )
                    );

                $buyer =
                    mb_strtolower(
                        $this->text(
                            $input,
                            'buyer_name'
                        )
                    );

                $transfer =
                    $this->number(
                        $input,
                        'transfer_units'
                    );

                $sellerUnits =
                    $this->number(
                        $input,
                        'seller_units'
                    );

                if (
                    $seller === ''
                    || $buyer === ''
                ) {
                    $errors[] =
                        'Share transfer အတွက် Seller နဲ့ Buyer လိုအပ်ပါတယ်။';
                }

                if (
                    $seller !== ''
                    && $seller === $buyer
                ) {
                    $errors[] =
                        'Seller နဲ့ Buyer တစ်ယောက်တည်း မဖြစ်ရပါဘူး။';
                }

                if ($transfer <= 0) {
                    $errors[] =
                        'Transfer Units 0 ထက်ကြီးရပါမယ်။';
                }

                if ($transfer > $sellerUnits) {
                    $errors[] =
                        'Seller ပိုင်ဆိုင်ထားတာထက်ပိုပြီး units လွှဲမရပါဘူး။';
                }

                break;

            case 'first_refusal_workflow':
                if (
                    $this->text(
                        $input,
                        'offer_date'
                    ) === ''
                ) {
                    $errors[] =
                        'Right of First Refusal workflow အတွက် Offer Date လိုအပ်ပါတယ်။';
                }

                if (
                    $this->number(
                        $input,
                        'transfer_units'
                    ) <= 0
                ) {
                    $errors[] =
                        'Offer လုပ်မယ့် units 0 ထက်ကြီးရပါမယ်။';
                }

                break;

            case 'transfer_approval_matrix':
                $this->requireRows(
                    $input,
                    'rules',
                    1,
                    'Transfer Approval'
                );

                break;

            case 'share_valuation_calculator':
                if (
                    $this->number(
                        $input,
                        'business_value'
                    ) <= 0
                ) {
                    $errors[] =
                        'Share valuation အတွက် Business Equity Value လိုအပ်ပါတယ်။';
                }

                if (
                    $this->number(
                        $input,
                        'total_units'
                    ) <= 0
                ) {
                    $errors[] =
                        'Share valuation အတွက် Total Units လိုအပ်ပါတယ်။';
                }

                if (
                    $this->number(
                        $input,
                        'transfer_units'
                    ) <= 0
                ) {
                    $errors[] =
                        'Valuation လုပ်မယ့် Transfer Units 0 ထက်ကြီးရပါမယ်။';
                }

                break;

            case 'conflict_escalation_ladder':
                $this->requireRows(
                    $input,
                    'steps',
                    2,
                    'Conflict Escalation'
                );

                break;

            case 'issue_priority_matrix':
                $this->requireRows(
                    $input,
                    'issues',
                    1,
                    'Issue Priority'
                );

                break;
        }
    }

    private function validateRecord(
        string $toolKey,
        array $input,
        array &$errors
    ): void {
        switch ($toolKey) {
            case 'meeting_decision_log':
            case 'decision_history':
                $this->requireText(
                    $input,
                    'decision_date',
                    'Decision Date',
                    $errors
                );

                $this->requireText(
                    $input,
                    'decision',
                    'Decision',
                    $errors
                );

                break;

            case 'transfer_history_tracker':
                $this->requireText(
                    $input,
                    'transfer_date',
                    'Transfer Date',
                    $errors
                );

                $this->requireText(
                    $input,
                    'from_holder',
                    'From Holder',
                    $errors
                );

                $this->requireText(
                    $input,
                    'to_holder',
                    'To Holder',
                    $errors
                );

                if (
                    mb_strtolower(
                        $this->text(
                            $input,
                            'from_holder'
                        )
                    )
                    ===
                    mb_strtolower(
                        $this->text(
                            $input,
                            'to_holder'
                        )
                    )
                    && $this->text(
                        $input,
                        'from_holder'
                    ) !== ''
                ) {
                    $errors[] =
                        'Transfer History မှာ From နဲ့ To holder တူလို့မရပါဘူး။';
                }

                if (
                    $this->number(
                        $input,
                        'units'
                    ) <= 0
                ) {
                    $errors[] =
                        'Transfer History မှာ transferred units 0 ထက်ကြီးရပါမယ်။';
                }

                break;

            case 'dispute_log':
                $this->requireText(
                    $input,
                    'issue_date',
                    'Issue Date',
                    $errors
                );

                $this->requireText(
                    $input,
                    'issue_title',
                    'Issue Title',
                    $errors
                );

                $this->requireText(
                    $input,
                    'parties',
                    'Involved Parties',
                    $errors
                );

                break;

            case 'resolution_tracker':
                $this->requireText(
                    $input,
                    'issue_title',
                    'Issue',
                    $errors
                );

                $this->requireText(
                    $input,
                    'next_action',
                    'Next Action',
                    $errors
                );

                $this->requireText(
                    $input,
                    'action_owner',
                    'Action Owner',
                    $errors
                );

                $this->requireText(
                    $input,
                    'target_date',
                    'Target Date',
                    $errors
                );

                break;
        }
    }

    private function validateAmountRanges(
        array $input,
        string $key,
        array &$errors,
        array &$warnings
    ): void {
        $rules = $this->rows(
            $input,
            $key
        );

        if ($rules === []) {
            $errors[] =
                'Approval rules အနည်းဆုံးတစ်ခု သတ်မှတ်ရပါမယ်။';

            return;
        }

        usort(
            $rules,
            fn (array $a, array $b): int =>
                $this->number(
                    $a,
                    'min_amount'
                )
                <=>
                $this->number(
                    $b,
                    'min_amount'
                )
        );

        $previousMax = null;

        foreach ($rules as $index => $rule) {
            $min =
                $this->number(
                    $rule,
                    'min_amount'
                );

            $maxRaw =
                $rule['max_amount'] ?? null;

            $max =
                $maxRaw === ''
                || $maxRaw === null
                    ? null
                    : $this->number(
                        $rule,
                        'max_amount'
                    );

            if (
                $max !== null
                && $max < $min
            ) {
                $errors[] =
                    'Approval range #'
                    .($index + 1)
                    .' မှာ maximum amount က minimum amount ထက်ငယ်နေပါတယ်။';
            }

            if ($previousMax !== null) {
                if ($min < $previousMax) {
                    $errors[] =
                        'Approval amount ranges တွေ overlap ဖြစ်နေပါတယ်။';
                } elseif ($min > $previousMax) {
                    $warnings[] =
                        'Approval ranges ကြားမှာ amount gap ရှိနေပါတယ်။';
                }
            }

            if ($max !== null) {
                $previousMax = $max;
            }
        }
    }

    private function requireNamedRows(
        array $rows,
        string $nameKey,
        int $minimum,
        string $label
    ): void {
        if (count($rows) < $minimum) {
            $this->policyErrors[] =
                $label
                .' အတွက် အနည်းဆုံး '
                .$minimum
                .' records လိုအပ်ပါတယ်.';

            return;
        }

        $names = [];

        foreach ($rows as $row) {
            $name = trim(
                (string) (
                    $row[$nameKey] ?? ''
                )
            );

            if ($name === '') {
                $this->policyErrors[] =
                    $label
                    .' မှာ record တစ်ခုချင်းစီအတွက် name လိုအပ်ပါတယ်.';

                continue;
            }

            $normalized =
                mb_strtolower($name);

            if (
                in_array(
                    $normalized,
                    $names,
                    true
                )
            ) {
                $this->policyErrors[] =
                    $label
                    .' မှာ duplicate name ရှိနေပါတယ်.';
            }

            $names[] = $normalized;
        }
    }

    private function requireRows(
        array $input,
        string $key,
        int $minimum,
        string $label
    ): void {
        if (
            count(
                $this->rows(
                    $input,
                    $key
                )
            )
            < $minimum
        ) {
            $this->policyErrors[] =
                $label
                .' အတွက် အနည်းဆုံး '
                .$minimum
                .' record လိုအပ်ပါတယ်.';
        }
    }

    private function rows(
        array $input,
        string $key
    ): array {
        $rows = is_array(
            $input[$key] ?? null
        )
            ? $input[$key]
            : [];

        return array_values(array_filter(
            $rows,
            fn ($row): bool =>
                is_array($row)
                && $this->arrayHasMeaningfulValue(
                    $row
                )
        ));
    }

    private function requireText(
        array $input,
        string $key,
        string $label,
        array &$errors
    ): void {
        if (
            $this->text(
                $input,
                $key
            ) === ''
        ) {
            $errors[] =
                $label.' လိုအပ်ပါတယ်။';
        }
    }

    private function text(
        array $input,
        string $key
    ): string {
        return trim(
            (string) ($input[$key] ?? '')
        );
    }

    private function number(
        array $input,
        string $key
    ): float {
        $value = $input[$key] ?? 0;

        return is_numeric($value)
            ? (float) $value
            : 0.0;
    }

    private function hasBusinessSpecificInput(
        array $input,
        array $defaults
    ): bool {
        foreach ($input as $key => $value) {
            $default =
                $defaults[$key] ?? null;

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $row) {
                        if (
                            is_array($row)
                            && $this->arrayHasMeaningfulValue(
                                $row
                            )
                        ) {
                            return true;
                        }
                    }

                    continue;
                }

                if (
                    $this->hasBusinessSpecificInput(
                        $value,
                        is_array($default)
                            ? $default
                            : []
                    )
                ) {
                    return true;
                }

                continue;
            }

            if (
                ! $this->isMeaningful(
                    $value
                )
            ) {
                continue;
            }

            if (
                (string) $value
                !== (string) $default
            ) {
                return true;
            }
        }

        return false;
    }

    private function arrayHasMeaningfulValue(
        array $values
    ): bool {
        foreach ($values as $value) {
            if (is_array($value)) {
                if (
                    $this->arrayHasMeaningfulValue(
                        $value
                    )
                ) {
                    return true;
                }

                continue;
            }

            if (
                $this->isMeaningful(
                    $value
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function isMeaningful(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return trim((string) $value) !== '';
    }
}
