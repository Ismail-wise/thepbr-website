/*
 * thePBR Premium Tool Design System V1 — pilot behavior
 * UI-only progressive enhancement for six representative tools.
 * No calculations, field names, persistence or approval endpoints are changed.
 */
(() => {
    'use strict';

    const pilots = {
        'working-capital-calculator': 'calculator',
        'decision-rights-matrix': 'matrix',
        'ownership-before-after-chart': 'visual',
        'exit-timeline': 'planner',
        'financial-control-checklist': 'checklist',
        'meeting-decision-log': 'record',
    };

    const familyLabels = {
        calculator: 'CALCULATOR',
        matrix: 'MATRIX / BUILDER',
        visual: 'VISUAL COMPARISON',
        planner: 'PLANNER / TIMELINE',
        checklist: 'READINESS CHECK',
        record: 'OPERATING RECORD',
    };

    const ready = (fn) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    };

    const resolvePilot = () => {
        const path = window.location.pathname.toLowerCase();
        return Object.entries(pilots).find(([slug]) => path.includes(`/${slug}`)) || null;
    };

    const updateRepeaterRows = (page) => {
        page.querySelectorAll('[data-repeater-rows]').forEach((rows) => {
            [...rows.querySelectorAll(':scope > [data-repeater-row]')].forEach((row, index) => {
                const number = row.querySelector('.pbr-os-row-number');
                if (number) number.textContent = String(index + 1).padStart(2, '0');

                const controls = [...row.querySelectorAll('input:not([type="hidden"]), select, textarea')];
                const meaningful = controls.filter((control) => String(control.value || '').trim() !== '');
                row.dataset.pbrRowComplete = meaningful.length > 0 ? 'true' : 'false';

                const columnCount = Math.max(1, row.querySelectorAll('.pbr-os-mini-field').length);
                row.style.setProperty('--pbr-ds-cols', String(Math.min(columnCount, 5)));
            });
        });
    };

    const setupRepeaterEnhancement = (page) => {
        updateRepeaterRows(page);

        page.addEventListener('input', (event) => {
            if (event.target.closest('[data-repeater-row]')) updateRepeaterRows(page);
        });
        page.addEventListener('change', (event) => {
            if (event.target.closest('[data-repeater-row]')) updateRepeaterRows(page);
        });
        page.addEventListener('click', (event) => {
            if (event.target.closest('[data-repeater-add], [data-repeater-remove]')) {
                window.setTimeout(() => updateRepeaterRows(page), 0);
            }
        });

        page.querySelectorAll('[data-repeater-rows]').forEach((rows) => {
            if (!('MutationObserver' in window)) return;
            const observer = new MutationObserver(() => updateRepeaterRows(page));
            observer.observe(rows, { childList: true });
        });
    };

    const setupChecklistProgress = (page) => {
        page.querySelectorAll('.pbr-os-checklist').forEach((checklist) => {
            if (checklist.previousElementSibling?.classList.contains('pbr-ds-check-progress')) return;

            const boxes = [...checklist.querySelectorAll('input[type="checkbox"]')];
            if (!boxes.length) return;

            const progress = document.createElement('div');
            progress.className = 'pbr-ds-check-progress';
            progress.innerHTML = `
                <span>Business readiness progress</span>
                <strong data-pbr-check-count>0 / ${boxes.length} confirmed</strong>
                <div class="pbr-ds-check-track" aria-hidden="true"><i></i></div>
            `;
            checklist.insertAdjacentElement('beforebegin', progress);

            const count = progress.querySelector('[data-pbr-check-count]');
            const meter = progress.querySelector('.pbr-ds-check-track i');

            const update = () => {
                const checked = boxes.filter((box) => box.checked).length;
                const percent = boxes.length ? (checked / boxes.length) * 100 : 0;
                count.textContent = `${checked} / ${boxes.length} confirmed`;
                meter.style.width = `${percent}%`;
            };

            boxes.forEach((box) => box.addEventListener('change', update));
            update();
        });
    };

    const addFamilyBadge = (page, family) => {
        const kickers = page.querySelector('.pbr-os-kickers, .pbr-tool-page-head .portal-kicker');
        if (!kickers || page.querySelector('.pbr-ds-family-badge')) return;

        const badge = document.createElement('span');
        badge.className = 'pbr-ds-family-badge';
        badge.textContent = familyLabels[family] || 'PBR TOOL';

        if (kickers.classList.contains('pbr-os-kickers')) {
            kickers.appendChild(badge);
        } else {
            kickers.insertAdjacentElement('afterend', badge);
        }
    };

    const markResultState = (page) => {
        const result = page.querySelector('#result, #capital-tool-result');
        if (!result) return;
        page.classList.add('pbr-ds-has-result');
    };

    ready(() => {
        const pilot = resolvePilot();
        if (!pilot) return;

        const [slug, family] = pilot;
        const page = document.querySelector('[data-pbr-premium-tool]');
        if (!page) return;

        page.classList.add('pbr-ds-v1');
        page.dataset.pbrDesignSystem = 'v1';
        page.dataset.pbrToolFamily = family;
        page.dataset.pbrPilotTool = slug;

        addFamilyBadge(page, family);
        setupRepeaterEnhancement(page);
        if (family === 'checklist') setupChecklistProgress(page);
        markResultState(page);
    });
})();
