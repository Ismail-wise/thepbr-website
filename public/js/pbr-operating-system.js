(() => {
    'use strict';

    const systemNames = {
        1: { mm: 'မတည်ငွေနှင့် ရင်းနှီးငွေ', en: 'Capital & Funding' },
        2: { mm: 'ပိုင်ဆိုင်မှုနှင့် အစုရှယ်ယာ', en: 'Ownership & Equity' },
        3: { mm: 'Partner တာဝန်နှင့် တန်ဖိုးထည့်ဝင်မှု', en: 'Partner Roles & Contributions' },
        4: { mm: 'အမြတ်၊ လစာနှင့် အရှုံး ခွဲဝေမှု', en: 'Profit & Distribution' },
        5: { mm: 'ငွေကြေး ထိန်းချုပ်မှု', en: 'Financial Controls' },
        6: { mm: 'အုပ်ချုပ်မှုနှင့် ဆုံးဖြတ်ချက် စနစ်', en: 'Governance & Decision Making' },
        7: { mm: 'Partner ထွက်ခွာမှုနှင့် Buyout', en: 'Exit & Buyout' },
        8: { mm: 'လုပ်ငန်းဆက်လက်မှုနှင့် အန္တရာယ်ကာကွယ်မှု', en: 'Continuity & Risk' },
        9: { mm: 'အစုရှယ်ယာ လွှဲပြောင်းမှု', en: 'Share Transfers' },
        10: { mm: 'Partner အငြင်းပွားမှု ဖြေရှင်းရေး', en: 'Dispute Management' },
    };

    function refreshNumbers(repeater) {
        repeater.querySelectorAll('[data-repeater-row]').forEach((row, index) => {
            const badge = row.querySelector('.pbr-os-row-number');
            if (badge) badge.textContent = String(index + 1);
        });
    }

    function cleanBusinessModuleNames() {
        const technicalSuffix = /\s+(Calculator|Planner|Matrix|Chart|Builder|Simulator|Dashboard|Checklist|Tracker|Scorecard|Timeline|Log|Detector|Analysis)$/i;

        document.querySelectorAll('.pbr-business-module-en,.pbr-business-module h4').forEach((element) => {
            const text = element.textContent.trim();
            element.textContent = text.replace(technicalSuffix, '');
        });
    }

    function replaceText(root, replacements) {
        if (!root) return;

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);

        nodes.forEach((node) => {
            let value = node.nodeValue;
            if (!value) return;

            replacements.forEach(([from, to]) => {
                value = typeof from === 'string'
                    ? value.replaceAll(from, to)
                    : value.replace(from, to);
            });

            node.nodeValue = value;
        });
    }

    function clarityFirstBusinessLanguage(root) {
        replaceText(root, [
            ['Agreed Business Rule', 'Active Business Rule'],
            ['Agreed Rule', 'Active Rule'],
            ['Current Agreed Business Rule', 'လက်ရှိ အသုံးပြုနေသော Business Rule'],
            ['Partner Read-Only View', 'Partner ကြည့်ရှုရန်သာ'],
            ['Permission Safe', 'Read-only'],
            ['Workflow', 'အသုံးပြုပုံ'],
            ['Actual business information', 'တကယ်အသုံးပြုမည့် Business Data'],
            ['Calculate / Review', 'Result စစ်ရန်'],
            ['Logic + warnings + comparison', 'တွက်ချက်မှု၊ သတိပေးချက်နဲ့ နှိုင်းယှဉ်မှု'],
            ['Save Draft', 'Draft သိမ်းရန်'],
            ['Scenario မပျောက်အောင်သိမ်း', 'အပြီးမသတ်သေးဘဲ သိမ်းထားရန်'],
            ['Saved Scenarios', 'သိမ်းထားသော Draft များ'],
            ['Rule History', 'Rule History · မှတ်တမ်း'],
            ['Business Inputs', 'လိုအပ်သော Business Data'],
            ['Scenario ကိုပြင်နေပါတယ်', 'သိမ်းထားသော Draft ကို ပြင်နေသည်'],
            ['Scenario အသစ်တည်ဆောက်ပါ', 'အချက်အလက်အသစ် ထည့်ပါ'],
            ['Tool တစ်ခုချင်းစီမှာ လိုအပ်တဲ့ data ပဲမေးထားပါတယ်။ Empty field မဖြည့်ချင်ရင် 0 / blank ထားနိုင်တဲ့နေရာတွေရှိပါတယ်။', 'ဒီ Business ဆုံးဖြတ်ချက်အတွက် လိုအပ်တဲ့အချက်အလက်ပဲ ထည့်ပါ။ မသေချာတဲ့ field ရှိရင် အောက်ကရှင်းလင်းချက်ကို ကြည့်ပြီးမှ ဖြည့်ပါ။'],
            ['Scenario အမည်', 'Draft အမည်'],
            ['Based on the information entered for this scenario', 'ထည့်ထားသော အချက်အလက်များအပေါ် အခြေခံထားသည်'],
            ['Business Note', 'Business အကြံပြုချက်'],
            ['Create Draft Output', 'Draft Result သိမ်းရန်'],
            ['Approve as Agreed Business Rule', 'Rule အဖြစ် အတည်ပြုအသုံးပြုရန်'],
            ['နောက် Chapters', 'အခြား Business Systems'],
            ['နောက် Chapter', 'အခြား Business System'],
            [/Chapter\s+\d+\s+ရဲ့ connected operating data/g, 'ဒီ Business System ရဲ့ အသုံးပြုနေသော data'],
            ['Important', 'သတိပြုရန်'],
        ]);
    }

    function professionalizeOperatingToolPage() {
        const page = document.querySelector('.pbr-os-page');
        if (!page) return;

        const chapterPill = page.querySelector('.pbr-os-chapter-pill');
        let system = null;

        if (chapterPill) {
            const match = chapterPill.textContent.match(/Chapter\s*0?(\d+)/i);
            if (match) system = systemNames[Number(match[1])] ?? null;
            chapterPill.textContent = system
                ? `${system.mm} · ${system.en}`
                : 'Business System';
        }

        const typePill = page.querySelector('.pbr-os-type-pill');
        if (typePill) typePill.hidden = true;

        const breadcrumb = page.querySelector('.pbr-os-breadcrumb');
        if (breadcrumb) {
            breadcrumb.querySelectorAll('a').forEach((link) => {
                if (link.textContent.includes('10-Chapter System')) {
                    link.textContent = 'Business Operating System';
                }
            });

            breadcrumb.querySelectorAll('span').forEach((span) => {
                if (/^Chapter\s+\d+$/i.test(span.textContent.trim())) {
                    span.textContent = system ? system.mm : 'Business System';
                }
            });
        }

        const businessContext = page.querySelector('.pbr-os-business-context');
        if (businessContext) {
            replaceText(businessContext, [
                ['Planning a New Partnership', 'Partnership အသစ် စီစဉ်နေသည်'],
                ['Managing an Existing Partnership', 'ရှိပြီးသား Partnership ကို စီမံနေသည်'],
            ]);
        }

        clarityFirstBusinessLanguage(page);

        const sideNew = page.querySelector('.pbr-os-side-head a');
        if (sideNew && sideNew.textContent.trim() === 'New') sideNew.textContent = 'အသစ်';

        const primaryAction = page.querySelector('.pbr-os-form-actions .pbr-os-btn.primary');
        if (primaryAction) primaryAction.textContent = 'Result စစ်ရန်';

        const resultLabel = page.querySelector('.pbr-os-result-hero > span');
        if (resultLabel && resultLabel.textContent.trim() === 'Result') {
            resultLabel.textContent = 'တွက်ချက်ရလဒ်';
        }

        page.querySelectorAll('.pbr-os-table-head > span').forEach((label) => {
            label.textContent = label.textContent.replace(/\brows\b/i, 'အတန်း');
        });

        const approval = page.querySelector('.pbr-os-approval-zone');
        if (approval) {
            const heading = approval.querySelector('h3');
            const paragraph = approval.querySelector('p');
            if (heading) heading.textContent = 'ဒီ Draft ကို လုပ်ငန်းမှာ တကယ်အသုံးပြုမလား?';
            if (paragraph) paragraph.innerHTML = 'Draft က စမ်းသပ်/ပြင်ဆင်နိုင်တဲ့ version ဖြစ်ပါတယ်။ <b>Rule အဖြစ် အတည်ပြုအသုံးပြုရန်</b> ကိုရွေးမှ PBR AI နဲ့ အခြား Business Systems တွေက လက်ရှိ official business data အဖြစ်ယူသုံးပါမယ်။';
        }

        const emptyState = page.querySelector('.pbr-os-empty-state');
        if (emptyState) {
            const heading = emptyState.querySelector('h2');
            const paragraph = emptyState.querySelector('p');
            if (heading) heading.textContent = 'ဒီ Business System အတွက် အသုံးပြုနေသော Rule မရှိသေးပါ';
            if (paragraph) paragraph.textContent = 'Owner/Admin က Rule တစ်ခုအတည်ပြုပြီး အသုံးပြုလာတဲ့အခါ ဒီနေရာမှာ လက်ရှိ business data ကိုမြင်ရပါမယ်။ Draft တွေကို Partner account မှာ မပြပါဘူး။';
        }
    }

    function professionalizeCapitalToolPage() {
        const page = document.querySelector('.pbr-tools-section');
        if (!page) return;

        page.querySelectorAll('.pbr-tools-back').forEach((link) => {
            if (link.textContent.includes('PBR Business Tools') || link.textContent.includes('10-Chapter')) {
                link.textContent = '← Business Operating System သို့ ပြန်ရန်';
            }
        });

        page.querySelectorAll('.portal-kicker').forEach((kicker) => {
            if (/Chapter\s*1/i.test(kicker.textContent)) {
                kicker.textContent = 'မတည်ငွေနှင့် ရင်းနှီးငွေ · Capital & Funding';
            }
        });

        clarityFirstBusinessLanguage(page);
    }

    cleanBusinessModuleNames();
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
                'ဒီ Draft ကို လက်ရှိအသုံးပြုမယ့် Business Rule အဖြစ် အတည်ပြုမလား?\n\n' +
                'အတည်ပြုပြီးရင် PBR AI နဲ့ အခြား Business Systems တွေက ဒီ data ကို current business rule အဖြစ်အသုံးပြုနိုင်ပါမယ်။'
            );

            if (!accepted) event.preventDefault();
        });
    });
})();
