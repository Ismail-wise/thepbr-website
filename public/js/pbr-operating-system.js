(() => {
    'use strict';

    function refreshNumbers(repeater) {
        repeater.querySelectorAll('[data-repeater-row]').forEach((row, index) => {
            const badge = row.querySelector('.pbr-os-row-number');
            if (badge) badge.textContent = String(index + 1);
        });
    }

    function replaceBusinessLanguage(root) {
        if (!root) return;

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const textNodes = [];
        while (walker.nextNode()) textNodes.push(walker.currentNode);

        textNodes.forEach((node) => {
            let value = node.nodeValue;
            if (!value) return;

            value = value
                .replaceAll('Agreed Business Rule', 'Active Business Rule')
                .replaceAll('Agreed Rule', 'Active Rule')
                .replaceAll('နောက် Chapters', 'အခြား Business Systems')
                .replaceAll('နောက် Chapter', 'အခြား Business System')
                .replace(/Chapter\s+\d+\s+ရဲ့ connected operating data/g, 'ဒီ Business System ရဲ့ active operating data');

            node.nodeValue = value;
        });
    }

    function professionalizeOperatingToolPage() {
        const page = document.querySelector('.pbr-os-page');
        if (!page) return;

        const systemNames = {
            1: 'Capital & Funding',
            2: 'Ownership & Equity',
            3: 'Partner Roles & Contributions',
            4: 'Profit & Distribution',
            5: 'Financial Controls',
            6: 'Governance & Decision Making',
            7: 'Exit & Buyout',
            8: 'Continuity & Risk',
            9: 'Share Transfers',
            10: 'Dispute Management',
        };

        const chapterPill = page.querySelector('.pbr-os-chapter-pill');
        let systemName = null;

        if (chapterPill) {
            const match = chapterPill.textContent.match(/Chapter\s*0?(\d+)/i);
            if (match) systemName = systemNames[Number(match[1])] ?? 'Business System';
            chapterPill.textContent = systemName ?? 'Business System';
        }

        const breadcrumb = page.querySelector('.pbr-os-breadcrumb');
        if (breadcrumb) {
            breadcrumb.querySelectorAll('a').forEach((link) => {
                if (link.textContent.includes('10-Chapter System')) {
                    link.textContent = 'Business Operating System';
                }
            });

            breadcrumb.querySelectorAll('span').forEach((span) => {
                if (/^Chapter\s+\d+$/i.test(span.textContent.trim())) {
                    span.textContent = systemName ?? 'Business System';
                }
            });
        }

        replaceBusinessLanguage(page);
    }

    function professionalizeCapitalToolPage() {
        const page = document.querySelector('.pbr-tools-section');
        if (!page) return;

        page.querySelectorAll('.pbr-tools-back').forEach((link) => {
            if (link.textContent.includes('PBR Business Tools') || link.textContent.includes('10-Chapter')) {
                link.textContent = '← Back to Business Operating System';
            }
        });

        page.querySelectorAll('.portal-kicker').forEach((kicker) => {
            if (/Chapter\s*1/i.test(kicker.textContent)) {
                kicker.textContent = 'Capital & Funding';
            }
        });

        replaceBusinessLanguage(page);
    }

    professionalizeOperatingToolPage();
    professionalizeCapitalToolPage();

    document.querySelectorAll('[data-pbr-repeater]').forEach((repeater) => {
        const rows = repeater.querySelector('[data-repeater-rows]');
        const template = repeater.querySelector('[data-repeater-template]');
        const addButton = repeater.querySelector('[data-repeater-add]');
        let nextIndex = rows ? rows.querySelectorAll('[data-repeater-row]').length : 0;

        if (addButton && rows && template) {
            addButton.addEventListener('click', () => {
                const html = template.innerHTML
                    .replaceAll('__INDEX__', String(nextIndex))
                    .replaceAll('__NUMBER__', String(nextIndex + 1));
                rows.insertAdjacentHTML('beforeend', html);
                nextIndex += 1;
                refreshNumbers(repeater);

                const newRow = rows.lastElementChild;
                const firstInput = newRow?.querySelector('input,select,textarea');
                if (firstInput) firstInput.focus();
            });
        }

        repeater.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-repeater-remove]');
            if (!removeButton) return;

            const row = removeButton.closest('[data-repeater-row]');
            if (!row) return;

            const allRows = rows?.querySelectorAll('[data-repeater-row]') ?? [];
            if (allRows.length <= 1) {
                row.querySelectorAll('input').forEach((input) => { input.value = ''; });
                row.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
                return;
            }

            row.remove();
            refreshNumbers(repeater);
        });

        refreshNumbers(repeater);
    });

    document.querySelectorAll('[data-confirm-agreed]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const accepted = window.confirm(
                'ဒီ Scenario ကို Active Business Rule အဖြစ် အတည်ပြုမလား?\n\n' +
                'အတည်ပြုပြီးရင် အခြား Business Systems နဲ့ PBR AI Advisor က current business rule အဖြစ်အသုံးပြုနိုင်ပါမယ်။'
            );

            if (!accepted) event.preventDefault();
        });
    });
})();
