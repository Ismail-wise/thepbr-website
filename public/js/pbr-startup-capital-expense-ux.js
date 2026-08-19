/*
 * PBR Startup Capital — original expense-entry design polish
 *
 * The original expense-entry workflow is intentionally preserved.
 * This script only improves labels, Working Plan action hierarchy and
 * approved-rule visibility. It does not create a second expense-entry UI,
 * intercept Quick Start, rename form fields or change calculations.
 */
(() => {
    'use strict';

    const ready = (fn) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    ready(() => {
        const page = document.querySelector('.pbr-capital-plan-page');
        const builder = document.querySelector('#startup-capital-builder');
        const costsSection = builder?.querySelector('.pbr-capital-costs-section');

        if (!page || !builder || !costsSection) return;
        if (page.dataset.expenseDesign === 'ready') return;
        page.dataset.expenseDesign = 'ready';

        /* Keep the familiar disclosure, but explain the secondary fields more
         * clearly. This changes copy only; every original field stays intact. */
        builder.querySelectorAll('.pbr-capital-item-details > summary').forEach((summary) => {
            summary.innerHTML = 'Funding, Due Date နှင့် အသေးစိတ် <small>လိုအပ်မှ ဖြည့်ရန်</small><span>＋</span>';
        });

        /* Add lightweight row numbering for scanability. No data is stored. */
        const numberRows = () => {
            builder.querySelectorAll('[data-category]').forEach((category) => {
                category.querySelectorAll('[data-item]').forEach((item, index) => {
                    item.style.setProperty('--pbr-expense-row', `'${String(index + 1).padStart(2, '0')}'`);
                });
            });
        };

        numberRows();
        const observer = new MutationObserver(numberRows);
        observer.observe(costsSection, { childList: true, subtree: true });

        /* Working Plans: show the two decisions a business owner actually
         * needs. Less-frequent management actions remain available in More. */
        document.querySelectorAll('.pbr-scenario-row').forEach((row) => {
            const actions = row.querySelector('.pbr-scenario-actions');
            if (!actions || actions.dataset.simplified === 'true') return;
            actions.dataset.simplified = 'true';

            const open = actions.querySelector('a.pbr-scenario-open');
            if (open) {
                open.textContent = 'ဆက်လက်ပြင်ဆင်ရန်';
                open.classList.add('pbr-scenario-primary-edit');
            }

            const forms = [...actions.querySelectorAll(':scope > form')];
            const approve = forms.find((form) => form.action.includes('/approve'));
            const secondary = forms.filter((form) => form !== approve);

            if (approve) {
                const button = approve.querySelector('button');
                if (button) button.textContent = '✓ Current Rule အဖြစ် အတည်ပြုရန်';
                approve.classList.add('pbr-scenario-approve-primary');
            }

            if (!secondary.length) return;

            const more = document.createElement('details');
            more.className = 'pbr-scenario-more-menu';
            more.innerHTML = '<summary aria-label="More plan actions">•••</summary><div class="pbr-scenario-more-panel"></div>';
            const panel = more.querySelector('.pbr-scenario-more-panel');

            secondary.forEach((form) => {
                if (form.action.includes('/rename')) {
                    form.classList.add('pbr-more-rename');
                } else if (form.action.includes('/duplicate')) {
                    form.querySelector('button')?.replaceChildren(document.createTextNode('Version မိတ္တူပြုလုပ်ရန်'));
                } else if (form.action.includes('/output')) {
                    form.querySelector('button')?.replaceChildren(document.createTextNode('Review Snapshot သိမ်းရန်'));
                } else if ((form.querySelector('input[name="_method"]')?.value || '').toUpperCase() === 'DELETE') {
                    form.querySelector('button')?.replaceChildren(document.createTextNode('Draft ဖျက်ရန်'));
                }
                panel.appendChild(form);
            });

            actions.appendChild(more);
        });

        /* Approved state must be visible immediately after approval rather
         * than existing only as a flash message. */
        const activeRuleText = page.querySelector('.pbr-capital-business-card b')?.textContent || '';
        if (/Active Rule/i.test(activeRuleText) && !page.querySelector('.pbr-current-rule-banner')) {
            const workflow = page.querySelector('.pbr-capital-tool-flow');
            const currency = builder.dataset.currency || 'THB';
            const total = builder.querySelector('[data-live-total]')?.textContent?.trim() || `${currency} 0.00`;
            const funded = builder.querySelector('[data-live-funded]')?.textContent?.trim() || `${currency} 0.00`;
            const gap = builder.querySelector('[data-live-gap]')?.textContent?.trim() || `${currency} 0.00`;
            const revision = activeRuleText.match(/Rev\s*(\d+)/i)?.[1] || '';
            const workspaceId = window.location.pathname.match(/\/workspaces\/(\d+)\//)?.[1];

            const banner = document.createElement('section');
            banner.className = 'pbr-current-rule-banner';
            banner.innerHTML = `
                <div class="pbr-current-rule-copy">
                    <span class="pbr-current-rule-status">✓ CURRENT BUSINESS RULE</span>
                    <h2>Startup Capital Plan ကို အတည်ပြုပြီး အသုံးပြုနေသည်</h2>
                    <p>ဒီ Plan က လက်ရှိ approved Startup Capital position ဖြစ်ပါတယ်${revision ? ` · Revision ${escapeHtml(revision)}` : ''}။ ပြောင်းလဲလိုပါက Working Plan အသစ်အဖြစ် စတင်ပြင်ဆင်နိုင်ပါတယ်။</p>
                </div>
                <div class="pbr-current-rule-metrics">
                    <div><span>Required Capital</span><strong>${escapeHtml(total)}</strong></div>
                    <div><span>Funding Secured</span><strong>${escapeHtml(funded)}</strong></div>
                    <div><span>Funding Gap</span><strong>${escapeHtml(gap)}</strong></div>
                </div>
                <div class="pbr-current-rule-actions">
                    ${workspaceId ? `<a href="/workspaces/${workspaceId}/rulebook">Rulebook တွင်ကြည့်ရန်</a>` : ''}
                    <button type="button" data-start-working-plan>Working Plan အသစ်စရန်</button>
                </div>
            `;

            workflow?.insertAdjacentElement('afterend', banner);
            banner.querySelector('[data-start-working-plan]')?.addEventListener('click', () => {
                costsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.setTimeout(() => {
                    const firstInput = costsSection.querySelector('[data-item-name], [data-add-template], [data-add-category]');
                    firstInput?.focus();
                }, 250);
            });
        }
    });
})();
