/*
 * thePBR Premium Tool System V2
 * Coordinated rollout for the remaining 62 Business OS tools.
 * Startup Capital and Working Capital are frozen reference experiences.
 * UI-only progressive enhancement: no calculation, field name, persistence,
 * approval endpoint, route, database or Rulebook behavior is changed.
 */
(() => {
    'use strict';

    const tools = {
        'current-capital-position': 'calculator',
        'contingency-fund-calculator': 'calculator',
        'partner-contribution-matrix': 'matrix',
        'funding-gap-calculator': 'calculator',
        'capital-allocation-chart': 'visual',

        'equity-split-simulator': 'calculator',
        'cap-table-builder': 'matrix',
        'voting-power-calculator': 'calculator',
        'share-value-calculator': 'calculator',
        'future-dilution-simulator': 'calculator',
        'ownership-chart': 'visual',

        'sweat-equity-calculator': 'calculator',
        'time-contribution-tracker': 'record',
        'partner-contribution-scorecard': 'matrix',
        'role-responsibility-matrix': 'matrix',
        'vesting-calculator': 'calculator',
        'contribution-balance-chart': 'visual',

        'profit-distribution-calculator': 'calculator',
        'salary-profit-share-planner': 'planner',
        'retained-earnings-calculator': 'calculator',
        'reserve-fund-planner': 'planner',
        'loss-sharing-simulator': 'calculator',
        'distribution-scenario-comparison': 'calculator',

        'cashflow-dashboard': 'calculator',
        'monthly-budget-planner': 'planner',
        'budget-vs-actual-chart': 'visual',
        'expense-approval-matrix': 'matrix',
        'bank-authority-matrix': 'matrix',
        'financial-control-checklist': 'checklist',
        'large-payment-approval-rules': 'matrix',

        'partner-role-matrix': 'matrix',
        'decision-rights-matrix': 'matrix',
        'authority-level-builder': 'matrix',
        'voting-simulator': 'calculator',
        'meeting-decision-log': 'record',
        'deadlock-detector': 'calculator',
        'governance-structure-chart': 'visual',

        'partner-buyout-calculator': 'calculator',
        'exit-value-simulator': 'calculator',
        'notice-period-planner': 'planner',
        'exit-timeline': 'planner',
        'responsibility-handover-checklist': 'checklist',
        'business-continuity-planner': 'planner',

        'key-person-dependency-map': 'matrix',
        'succession-planner': 'planner',
        'emergency-authority-planner': 'planner',
        'ownership-transition-simulator': 'calculator',
        'continuity-checklist': 'checklist',
        'insurance-coverage-gap-calculator': 'calculator',

        'share-transfer-simulator': 'calculator',
        'ownership-before-after-chart': 'visual',
        'right-of-first-refusal-workflow': 'planner',
        'transfer-approval-matrix': 'matrix',
        'share-valuation-calculator': 'calculator',
        'transfer-history-tracker': 'record',

        'conflict-escalation-ladder': 'matrix',
        'dispute-log': 'record',
        'resolution-tracker': 'record',
        'deadlock-decision-tool': 'planner',
        'issue-priority-matrix': 'matrix',
        'decision-history': 'record',
        'escalation-timeline': 'planner',
    };

    const familyLabels = {
        calculator: 'CALCULATE & DECIDE',
        matrix: 'DEFINE & ASSIGN',
        visual: 'COMPARE & UNDERSTAND',
        planner: 'PLAN & EXECUTE',
        checklist: 'CHECK & PREPARE',
        record: 'RECORD & APPROVE',
    };

    const ready = (fn) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    };

    const resolveTool = () => {
        const path = window.location.pathname.toLowerCase();
        return Object.entries(tools).find(([slug]) => path.includes(`/${slug}`)) || null;
    };

    const hasMeaningfulValue = (root) => {
        const controls = root.querySelectorAll('input:not([type="hidden"]), select, textarea');
        return [...controls].some((control) => {
            if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) {
                return control.checked;
            }
            const value = String(control.value || '').trim();
            return value !== '' && value !== '0' && value !== '0.00';
        });
    };

    const addFamilyBadge = (page, family) => {
        if (page.querySelector('.pbr-ds-v2-family-badge')) return;

        const host = page.querySelector('.pbr-os-kickers, .pbr-tool-page-head .portal-kicker');
        if (!host) return;

        const badge = document.createElement('span');
        badge.className = 'pbr-ds-v2-family-badge';
        badge.textContent = familyLabels[family] || 'BUSINESS TOOL';

        if (host.classList.contains('pbr-os-kickers')) {
            host.appendChild(badge);
        } else {
            host.insertAdjacentElement('afterend', badge);
        }
    };

    const wrapOperatingContext = (page) => {
        const form = page.querySelector('#pbrOperatingToolForm, #chapter-one-tool-form');
        const context = form?.querySelector('.pbr-operating-context');
        if (!form || !context || context.closest('.pbr-ds-v2-governance')) return;

        const details = document.createElement('details');
        details.className = 'pbr-ds-v2-governance';
        details.open = hasMeaningfulValue(context);

        const summary = document.createElement('summary');
        summary.innerHTML = `
            <span>
                <strong>Operating Context & Action Plan</strong>
                <small>Owner, review date, evidence နဲ့ follow-up actions ကို decision ပြီးမှ သတ်မှတ်နိုင်ပါတယ်။</small>
            </span>
            <i>Show</i>
        `;

        context.replaceWith(details);
        details.append(summary, context);

        if (form.id === 'pbrOperatingToolForm') {
            details.classList.add('pbr-ds-v2-operating-governance');
        }
    };

    const moveChapterOneGovernanceAfterWorkspace = (page) => {
        const layout = page.querySelector('#capital-calculator-workspace');
        const details = page.querySelector('#chapter-one-tool-form > .pbr-ds-v2-governance');
        if (!layout || !details) return;
        layout.insertAdjacentElement('afterend', details);
    };

    const moveChapterOnePlanName = (page) => {
        const panel = page.querySelector('.pbr-calculator-panel');
        const scenario = page.querySelector('.pbr-scenario-box');
        const guide = panel?.querySelector('.pbr-calculator-action-guide');
        if (!panel || !scenario || scenario.classList.contains('pbr-ds-v2-plan-name')) return;

        scenario.classList.add('pbr-ds-v2-plan-name');
        const label = scenario.querySelector('label');
        if (label) label.textContent = 'Working Plan Name';
        const small = scenario.querySelector('small');
        if (small) small.textContent = 'ဒီ option ကို နောက်မှပြန်ဆက်နိုင်အောင် မှတ်မိလွယ်တဲ့ plan name တစ်ခုပေးပါ။';

        if (guide) panel.insertBefore(scenario, guide);
        else panel.appendChild(scenario);

        const duplicate = page.querySelector('.pbr-active-draft');
        if (duplicate) duplicate.hidden = true;
    };

    const simplifyChapterOnePlans = (page) => {
        const manager = page.querySelector('.pbr-scenario-manager');
        if (!manager) return;

        const intro = manager.querySelector('.pbr-scenario-manager-head p');
        if (intro) {
            intro.textContent = 'Working Draft က Current Rule ကို မပြောင်းပါဘူး။ ဆက်ပြင်ပါ၊ review လုပ်ပါ၊ လိုအပ်မှ approve လုပ်ပြီး Business Rule ဖြစ်စေပါ။';
        }

        manager.querySelectorAll('.pbr-scenario-row').forEach((row) => {
            const actions = row.querySelector('.pbr-scenario-actions');
            if (!actions || actions.querySelector('.pbr-ds-v2-plan-primary')) return;

            const open = actions.querySelector('.pbr-scenario-open');
            const approve = actions.querySelector('form[data-confirm-agreed]');
            if (open) open.textContent = 'Continue Editing';
            if (approve?.querySelector('button')) approve.querySelector('button').textContent = 'Review & Approve';

            const primary = document.createElement('div');
            primary.className = 'pbr-ds-v2-plan-primary';
            if (open) primary.appendChild(open);
            if (approve) primary.appendChild(approve);

            const more = document.createElement('details');
            more.className = 'pbr-ds-v2-plan-more';
            const summary = document.createElement('summary');
            summary.textContent = '•••';
            summary.setAttribute('aria-label', 'More Working Plan actions');
            const menu = document.createElement('div');
            menu.className = 'pbr-ds-v2-plan-menu';

            [...actions.children].forEach((child) => {
                if (child === open || child === approve || child === primary || child === more) return;
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

    const simplifyApproval = (page, family) => {
        page.querySelectorAll('.pbr-os-approval-zone').forEach((zone) => {
            const outputForm = [...zone.querySelectorAll('.pbr-os-approval-actions form')].find((form) =>
                (form.querySelector('button')?.textContent || '').includes('Review Output') ||
                (form.querySelector('button')?.textContent || '').includes('Draft Result')
            );
            if (outputForm) outputForm.hidden = true;

            const approveForm = zone.querySelector('form[data-confirm-agreed]');
            const approveButton = approveForm?.querySelector('button');
            if (approveButton) {
                approveButton.textContent = family === 'record'
                    ? '✓ Approve & Add to History'
                    : '✓ Approve as Current Rule';
            }
        });
    };

    const simplifyOperatingWorkspace = (page, family) => {
        const layout = page.querySelector('.pbr-os-layout');
        const sidebar = layout?.querySelector('.pbr-os-sidebar');
        const main = layout?.querySelector('.pbr-os-main');

        if (layout && sidebar && main && !layout.querySelector('.pbr-ds-v2-workspace-meta')) {
            const details = document.createElement('details');
            details.className = 'pbr-ds-v2-workspace-meta';
            if (family === 'record') details.open = true;

            const summary = document.createElement('summary');
            summary.innerHTML = '<span><strong>Working Plans & History</strong><small>Drafts, workflow status နဲ့ approved revisions</small></span><i>Show</i>';

            sidebar.replaceWith(details);
            details.append(summary, sidebar);
            main.insertAdjacentElement('afterend', details);
        }

        const intelligence = page.querySelector('.pbr-premium-tool-intelligence');
        if (intelligence && !intelligence.closest('.pbr-ds-v2-connected-context')) {
            const details = document.createElement('details');
            details.className = 'pbr-ds-v2-connected-context';
            const summary = document.createElement('summary');
            summary.innerHTML = '<span><strong>Connected Business Context</strong><small>Approved dependencies, guidance နဲ့ connected operating information</small></span><i>Show</i>';
            intelligence.replaceWith(details);
            details.append(summary, intelligence);
            layout?.insertAdjacentElement('afterend', details);
        }

        const resultButton = page.querySelector('#pbrOperatingToolForm > .pbr-os-form-actions > .pbr-os-btn.primary');
        if (resultButton) resultButton.textContent = 'Review Result →';

        const saveButton = page.querySelector('.pbr-os-save-cluster .pbr-os-btn.secondary');
        if (saveButton) saveButton.textContent = 'Save Working Plan';
    };

    const groupCalculatorPanels = (page, family) => {
        if (family !== 'calculator') return;
        const main = page.querySelector('.pbr-os-main');
        const input = main?.querySelector(':scope > .pbr-os-input-panel');
        const result = main?.querySelector(':scope > .pbr-os-result-panel');
        if (!main || !input || !result || main.querySelector('.pbr-ds-v2-primary-grid')) return;

        const grid = document.createElement('div');
        grid.className = 'pbr-ds-v2-primary-grid';
        main.insertBefore(grid, input);
        grid.append(input, result);
    };

    const enhanceRepeaters = (page) => {
        const update = () => {
            page.querySelectorAll('[data-repeater-rows]').forEach((rows) => {
                [...rows.querySelectorAll(':scope > [data-repeater-row]')].forEach((row, index) => {
                    const number = row.querySelector('.pbr-os-row-number');
                    if (number) number.textContent = String(index + 1).padStart(2, '0');
                    const cols = Math.max(1, row.querySelectorAll('.pbr-os-mini-field').length);
                    row.style.setProperty('--pbr-v2-cols', String(Math.min(cols, 5)));
                });
            });
        };

        update();
        page.addEventListener('click', (event) => {
            if (event.target.closest('[data-repeater-add], [data-repeater-remove]')) {
                window.setTimeout(update, 0);
            }
        });
    };

    const enhanceChecklist = (page) => {
        page.querySelectorAll('.pbr-os-checklist').forEach((checklist) => {
            if (checklist.previousElementSibling?.classList.contains('pbr-ds-v2-check-progress')) return;
            const boxes = [...checklist.querySelectorAll('input[type="checkbox"]')];
            if (!boxes.length) return;

            const progress = document.createElement('div');
            progress.className = 'pbr-ds-v2-check-progress';
            progress.innerHTML = '<span>Readiness Progress</span><strong data-v2-check-count></strong><div><i></i></div>';
            checklist.insertAdjacentElement('beforebegin', progress);

            const count = progress.querySelector('[data-v2-check-count]');
            const bar = progress.querySelector('i');
            const update = () => {
                const done = boxes.filter((box) => box.checked).length;
                count.textContent = `${done} / ${boxes.length} confirmed`;
                bar.style.width = `${boxes.length ? (done / boxes.length) * 100 : 0}%`;
            };
            boxes.forEach((box) => box.addEventListener('change', update));
            update();
        });
    };

    ready(() => {
        const resolved = resolveTool();
        if (!resolved) return;

        const [slug, family] = resolved;
        const page = document.querySelector('[data-pbr-premium-tool]');
        if (!page) return;

        page.classList.add('pbr-ds-v2', `pbr-ds-family-${family}`);
        page.dataset.pbrToolFamily = family;
        page.dataset.pbrV2Tool = slug;

        addFamilyBadge(page, family);
        wrapOperatingContext(page);
        enhanceRepeaters(page);
        simplifyApproval(page, family);

        if (family === 'checklist') enhanceChecklist(page);

        if (page.querySelector('#chapter-one-tool-form')) {
            moveChapterOnePlanName(page);
            moveChapterOneGovernanceAfterWorkspace(page);
            simplifyChapterOnePlans(page);
        } else {
            simplifyOperatingWorkspace(page, family);
            groupCalculatorPanels(page, family);
        }
    });
})();
