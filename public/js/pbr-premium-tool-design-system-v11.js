/*
 * thePBR Premium Tool Design System V1.1
 * Working Capital progressive information-architecture refinement.
 * UI-only: moves existing DOM nodes without changing form names, routes,
 * calculations, persistence or approval endpoints.
 */
(() => {
    'use strict';

    const isWorkingCapital = () =>
        window.location.pathname.toLowerCase().includes('/working-capital-calculator');

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    };

    const hasMeaningfulGovernanceData = (context) => {
        const selectors = [
            'input[name="operating_context[effective_date]"]',
            'input[name="operating_context[review_date]"]',
            'textarea[name="operating_context[decision_summary]"]',
            'textarea[name="operating_context[evidence]"]',
            'input[name^="operating_actions"][name$="[action]"]',
            'input[name^="operating_actions"][name$="[due_date]"]',
            'textarea[name^="operating_actions"][name$="[details]"]',
        ];

        return selectors.some((selector) =>
            [...context.querySelectorAll(selector)].some(
                (control) => String(control.value || '').trim() !== ''
            )
        );
    };

    const moveWorkingPlanNameIntoCalculator = (page) => {
        const panel = page.querySelector('.pbr-calculator-panel');
        const scenario = page.querySelector('.pbr-scenario-box');
        const guide = page.querySelector('.pbr-calculator-action-guide');

        if (!panel || !scenario || scenario.classList.contains('pbr-ds-working-plan-bar')) {
            return;
        }

        scenario.classList.add('pbr-ds-working-plan-bar');

        const label = scenario.querySelector('label');
        if (label) label.textContent = 'Working Plan Name';

        const helper = scenario.querySelector('small');
        if (helper) {
            helper.textContent = 'ဒီ calculation ကို နောက်မှပြန်ဆက်နိုင်အောင် မှတ်မိလွယ်တဲ့ plan name တစ်ခုပေးပါ။';
        }

        if (guide) {
            panel.insertBefore(scenario, guide);
        } else {
            panel.appendChild(scenario);
        }

        const activeDraft = page.querySelector('.pbr-active-draft');
        if (activeDraft) activeDraft.setAttribute('aria-hidden', 'true');
    };

    const moveGovernanceAfterCalculator = (page) => {
        const form = page.querySelector('#chapter-one-tool-form');
        const layout = page.querySelector('#capital-calculator-workspace');
        const context = page.querySelector('.pbr-operating-context');

        if (!form || !layout || !context || page.querySelector('.pbr-ds-v11-governance')) {
            return;
        }

        const details = document.createElement('details');
        details.className = 'pbr-ds-v11-governance';

        const summary = document.createElement('summary');
        summary.innerHTML = `
            <span>
                <strong>Operating Context & Action Plan</strong>
                <small>Owner, review date, evidence နဲ့ follow-up actions ကို decision ပြီးမှ သတ်မှတ်နိုင်ပါတယ်။</small>
            </span>
        `;

        details.appendChild(summary);
        details.appendChild(context);
        layout.insertAdjacentElement('afterend', details);

        if (hasMeaningfulGovernanceData(context)) {
            details.open = true;
        }
    };

    const simplifyApproval = (page) => {
        const zone = page.querySelector('.pbr-os-approval-zone');
        if (!zone) return;

        const heading = zone.querySelector('h3');
        if (heading) heading.textContent = 'ဒီ Working Capital ကို Current Business Rule အဖြစ် အသုံးပြုမလား?';

        const description = zone.querySelector('p');
        if (description) {
            description.textContent = 'Working Draft ကို approve လုပ်မှ ဒီ result ကို Capital & Funding ရဲ့ လက်ရှိ approved Working Capital Rule အဖြစ် အသုံးပြုပါမယ်။';
        }

        zone.querySelectorAll('.pbr-os-approval-actions form').forEach((form) => {
            const button = form.querySelector('button');
            if (!button) return;

            const text = button.textContent || '';
            if (text.includes('Draft Result')) {
                form.hidden = true;
            }

            if (form.matches('[data-confirm-agreed]') || form.querySelector('[data-confirm-agreed]')) {
                button.textContent = '✓ Approve as Current Rule';
            }
        });
    };

    const simplifyWorkingPlans = (page) => {
        const manager = page.querySelector('.pbr-scenario-manager');
        if (!manager) return;

        const intro = manager.querySelector('.pbr-scenario-manager-head p');
        if (intro) {
            intro.textContent = 'Working Draft က Current Rule ကို မပြောင်းပါဘူး။ ဆက်ပြင်ပါ၊ လိုအပ်ရင် approve လုပ်ပြီးမှ Business Rule ဖြစ်စေပါ။';
        }

        const count = manager.querySelector('.pbr-scenario-count');
        if (count) {
            const numeric = (count.textContent || '').match(/\d+/)?.[0];
            if (numeric) count.textContent = `${numeric} Draft${numeric === '1' ? '' : 's'}`;
        }

        manager.querySelectorAll('.pbr-scenario-row').forEach((row) => {
            const actions = row.querySelector('.pbr-scenario-actions');
            if (!actions || actions.querySelector('.pbr-ds-plan-primary')) return;

            const open = actions.querySelector('.pbr-scenario-open');
            const approveForm = actions.querySelector('form[data-confirm-agreed]');

            if (open) open.textContent = 'Continue Editing';

            if (approveForm) {
                const approveButton = approveForm.querySelector('button');
                if (approveButton) approveButton.textContent = 'Review & Approve';
            }

            const primary = document.createElement('div');
            primary.className = 'pbr-ds-plan-primary';

            if (open) primary.appendChild(open);
            if (approveForm) primary.appendChild(approveForm);

            const more = document.createElement('details');
            more.className = 'pbr-ds-plan-more';

            const summary = document.createElement('summary');
            summary.setAttribute('aria-label', 'More Working Plan actions');
            summary.textContent = '•••';

            const menu = document.createElement('div');
            menu.className = 'pbr-ds-plan-menu';

            [...actions.children].forEach((child) => {
                if (child === primary || child === more || child === open || child === approveForm) {
                    return;
                }

                const button = child.querySelector?.('button');
                if (button && (button.textContent || '').includes('Review Output')) {
                    button.textContent = 'Review Snapshot သိမ်းရန်';
                }

                menu.appendChild(child);
            });

            more.append(summary, menu);
            actions.append(primary, more);
        });
    };

    const simplifyActionGuide = (page) => {
        const guide = page.querySelector('.pbr-calculator-action-guide p');
        if (!guide) return;
        guide.textContent = 'Result စစ်ပြီး Working Plan အဖြစ် သိမ်းနိုင်ပါတယ်။ Save လုပ်တာနဲ့ Current Rule မပြောင်းပါဘူး။';
    };

    ready(() => {
        if (!isWorkingCapital()) return;

        const page = document.querySelector('[data-pbr-premium-tool]');
        if (!page) return;

        page.classList.add('pbr-ds-v11');
        page.dataset.pbrDesignSystemRefinement = 'v1.1';

        moveWorkingPlanNameIntoCalculator(page);
        moveGovernanceAfterCalculator(page);
        simplifyActionGuide(page);
        simplifyApproval(page);
        simplifyWorkingPlans(page);
    });
})();
