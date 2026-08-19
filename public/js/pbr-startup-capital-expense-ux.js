/*
 * PBR Startup Capital — premium expense-entry UX
 * Progressive enhancement only. Existing form names, calculations and backend
 * endpoints remain unchanged.
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
        const categoriesRoot = builder?.querySelector('[data-categories]');
        const quickStart = builder?.querySelector('.pbr-capital-quick-start');
        const costsSection = builder?.querySelector('.pbr-capital-costs-section');

        if (!page || !builder || !categoriesRoot || !quickStart || !costsSection) return;
        if (page.dataset.expenseUx === 'ready') return;
        page.dataset.expenseUx = 'ready';

        const currency = builder.dataset.currency || 'THB';
        const templateNames = [...builder.querySelectorAll('[data-add-template]')]
            .map((button) => (button.dataset.addTemplate || '').trim())
            .filter(Boolean);

        const categoryOptions = [...new Set(templateNames)];
        const categorySeedBase = 10000 + Math.floor(Date.now() / 1000) % 50000;
        let categorySeed = categorySeedBase;
        let itemSeed = 30000 + Math.floor(Date.now() / 1000) % 50000;

        const fastEntry = document.createElement('section');
        fastEntry.className = 'pbr-fast-expense-card';
        fastEntry.innerHTML = `
            <div class="pbr-fast-expense-head">
                <div>
                    <span class="portal-kicker">FAST EXPENSE ENTRY</span>
                    <h2>ကုန်ကျစရိတ်ကို မြန်မြန်ထည့်ပါ</h2>
                    <p>အရင်ဆုံး အမည်၊ Amount၊ Category နဲ့ အရေးပါမှုကိုပဲထည့်ပါ။ Funding နဲ့ Due Date ကို နောက်မှ Item တစ်ခုချင်းစီမှာ ဖြည့်နိုင်ပါတယ်။</p>
                </div>
                <span class="pbr-fast-expense-tip">Enter နှိပ်ပြီးလည်း ထည့်နိုင်သည်</span>
            </div>

            <div class="pbr-fast-expense-grid">
                <label class="pbr-fast-field pbr-fast-name">
                    <span>ကုန်ကျစရိတ်</span>
                    <input type="text" data-fast-expense-name maxlength="150" placeholder="ဥပမာ: ဆိုင် Deposit">
                </label>

                <label class="pbr-fast-field pbr-fast-amount">
                    <span>Amount</span>
                    <div class="pbr-fast-money">
                        <b>${escapeHtml(currency)}</b>
                        <input type="number" data-fast-expense-amount min="0" max="999999999999.99" step="0.01" placeholder="0.00">
                    </div>
                </label>

                <label class="pbr-fast-field pbr-fast-category">
                    <span>Category</span>
                    <select data-fast-expense-category>
                        ${categoryOptions.map((name) => `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`).join('')}
                        <option value="__custom__">အခြား / Custom Category</option>
                    </select>
                </label>

                <label class="pbr-fast-field pbr-fast-custom" hidden>
                    <span>Custom Category</span>
                    <input type="text" data-fast-expense-custom maxlength="120" placeholder="ဥပမာ: Delivery Setup">
                </label>

                <label class="pbr-fast-field pbr-fast-priority">
                    <span>အရေးပါမှု</span>
                    <select data-fast-expense-priority>
                        <option value="essential">မဖြစ်မနေလို</option>
                        <option value="optional">Optional</option>
                    </select>
                </label>

                <button type="button" class="pbr-fast-expense-add" data-fast-expense-add>
                    <span>＋</span>
                    ကုန်ကျစရိတ်ထည့်ရန်
                </button>
            </div>

            <div class="pbr-fast-expense-feedback" data-fast-expense-feedback aria-live="polite">
                Category တစ်ခုရွေးပြီး ကုန်ကျစရိတ်ကို စတင်ထည့်ပါ။
            </div>
        `;

        costsSection.insertAdjacentElement('beforebegin', fastEntry);

        const nameInput = fastEntry.querySelector('[data-fast-expense-name]');
        const amountInput = fastEntry.querySelector('[data-fast-expense-amount]');
        const categorySelect = fastEntry.querySelector('[data-fast-expense-category]');
        const customField = fastEntry.querySelector('.pbr-fast-custom');
        const customInput = fastEntry.querySelector('[data-fast-expense-custom]');
        const prioritySelect = fastEntry.querySelector('[data-fast-expense-priority]');
        const addButton = fastEntry.querySelector('[data-fast-expense-add]');
        const feedback = fastEntry.querySelector('[data-fast-expense-feedback]');

        const money = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const moneyText = (value) => `${currency} ${money.format(Math.max(0, Number(value) || 0))}`;

        const dispatchInput = (el) => {
            el?.dispatchEvent(new Event('input', { bubbles: true }));
            el?.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const existingCategory = (name) => [...builder.querySelectorAll('[data-category]')]
            .find((category) => {
                const input = category.querySelector('[data-category-name]');
                return (input?.value || '').trim().toLowerCase() === name.trim().toLowerCase();
            });

        const itemMarkup = (categoryIndex) => {
            const itemIndex = itemSeed++;
            return `
                <article class="pbr-capital-item pbr-fast-created-item" data-item>
                    <div class="pbr-capital-item-main">
                        <div class="pbr-capital-field pbr-capital-item-name">
                            <label>ကုန်ကျစရိတ်</label>
                            <input type="text" name="categories[${categoryIndex}][items][${itemIndex}][name]" maxlength="150" placeholder="ဥပမာ: ဆိုင် Deposit" data-item-name>
                        </div>
                        <div class="pbr-capital-field pbr-capital-amount-field">
                            <label>Amount</label>
                            <div class="pbr-capital-money-input">
                                <span>${escapeHtml(currency)}</span>
                                <input type="number" name="categories[${categoryIndex}][items][${itemIndex}][amount]" min="0" max="999999999999.99" step="0.01" placeholder="0.00" data-item-amount>
                            </div>
                        </div>
                        <div class="pbr-capital-field pbr-capital-priority-field">
                            <label>အရေးပါမှု</label>
                            <select name="categories[${categoryIndex}][items][${itemIndex}][priority]" data-item-priority>
                                <option value="essential">မဖြစ်မနေလို</option>
                                <option value="optional">Optional</option>
                            </select>
                        </div>
                        <button type="button" class="pbr-capital-remove-item" data-remove-item aria-label="ဒီကုန်ကျစရိတ်ကို ဖယ်ရန်">×</button>
                    </div>
                    <div class="pbr-capital-item-live-line">
                        <span data-item-plan-label>Plan ထဲတွင် ${escapeHtml(currency)} 0.00</span>
                        <span data-item-funding-label>Funding မထည့်ရသေး</span>
                    </div>
                    <details class="pbr-capital-item-details">
                        <summary>Funding & timing ထည့်ရန် <small>Optional details</small><span>＋</span></summary>
                        <div class="pbr-capital-detail-grid">
                            <div class="pbr-capital-field">
                                <label>ကုန်ကျစရိတ်ပုံစံ</label>
                                <select name="categories[${categoryIndex}][items][${itemIndex}][frequency]" data-item-frequency>
                                    <option value="one_time">တစ်ကြိမ်တည်း</option>
                                    <option value="monthly">လစဉ်</option>
                                </select>
                            </div>
                            <div class="pbr-capital-field pbr-capital-months-field" data-months-field hidden>
                                <label>ဘယ်နှလ အရန်ထားမလဲ</label>
                                <input type="number" name="categories[${categoryIndex}][items][${itemIndex}][reserve_months]" value="3" min="1" max="24" step="1" data-item-months>
                            </div>
                            <div class="pbr-capital-field">
                                <label>ရရှိထားပြီး Funding</label>
                                <div class="pbr-capital-money-input">
                                    <span>${escapeHtml(currency)}</span>
                                    <input type="number" name="categories[${categoryIndex}][items][${itemIndex}][funded_amount]" min="0" max="999999999999.99" step="0.01" placeholder="0.00" data-item-funded>
                                </div>
                            </div>
                            <div class="pbr-capital-field">
                                <label>Funding Source</label>
                                <input type="text" name="categories[${categoryIndex}][items][${itemIndex}][funding_source]" maxlength="150" placeholder="ဥပမာ: Partner A, Bank Loan">
                            </div>
                            <div class="pbr-capital-field">
                                <label>လိုအပ်မည့်ရက် · Due Date</label>
                                <input type="date" name="categories[${categoryIndex}][items][${itemIndex}][due_date]" data-item-due>
                            </div>
                            <div class="pbr-capital-field pbr-capital-note-field">
                                <label>မှတ်ချက်</label>
                                <input type="text" name="categories[${categoryIndex}][items][${itemIndex}][note]" maxlength="500" placeholder="လိုအပ်ရင် မှတ်ချက်တိုတိုထည့်ပါ">
                            </div>
                        </div>
                    </details>
                </article>
            `;
        };

        const categoryMarkup = (name) => {
            const index = categorySeed++;
            return `
                <section class="pbr-capital-category pbr-fast-created-category" data-category data-category-index="${index}">
                    <header class="pbr-capital-category-head">
                        <div class="pbr-capital-category-name">
                            <span>ကုန်ကျစရိတ်အုပ်စု</span>
                            <input type="text" name="categories[${index}][name]" value="${escapeHtml(name)}" maxlength="120" required data-category-name>
                        </div>
                        <div class="pbr-capital-category-meta">
                            <div><small>Planned</small><strong data-category-subtotal>${escapeHtml(currency)} 0.00</strong></div>
                            <div><small>Funded</small><strong data-category-funded>${escapeHtml(currency)} 0.00</strong></div>
                            <button type="button" data-remove-category aria-label="ဒီအုပ်စုကို ဖယ်ရန်">×</button>
                        </div>
                    </header>
                    <div class="pbr-capital-items" data-items>${itemMarkup(index)}</div>
                    <footer class="pbr-capital-category-footer">
                        <button type="button" data-add-item>+ နောက်ထပ်ကုန်ကျစရိတ်</button>
                        <span>Item <b data-category-item-count>1</b> ခု</span>
                    </footer>
                </section>
            `;
        };

        const ensureCategory = (name) => {
            const found = existingCategory(name);
            if (found) return found;

            categoriesRoot.insertAdjacentHTML('beforeend', categoryMarkup(name));
            return categoriesRoot.lastElementChild;
        };

        const blankItem = (category) => [...category.querySelectorAll('[data-item]')]
            .find((item) => {
                const name = item.querySelector('[data-item-name]')?.value?.trim() || '';
                const amount = item.querySelector('[data-item-amount]')?.value?.trim() || '';
                return name === '' && amount === '';
            });

        const ensureItem = (category) => {
            const blank = blankItem(category);
            if (blank) return blank;

            const itemsRoot = category.querySelector('[data-items]');
            const categoryIndex = category.dataset.categoryIndex;
            if (!itemsRoot || categoryIndex === undefined) return null;

            itemsRoot.insertAdjacentHTML('beforeend', itemMarkup(categoryIndex));
            return itemsRoot.lastElementChild;
        };

        const currentCategoryName = () => {
            if (categorySelect.value !== '__custom__') return categorySelect.value.trim();
            return (customInput.value || '').trim();
        };

        const setFeedback = (message, state = 'neutral') => {
            feedback.textContent = message;
            feedback.dataset.state = state;
        };

        const addExpense = () => {
            const expenseName = (nameInput.value || '').trim();
            const amount = Math.max(0, Number(amountInput.value) || 0);
            const categoryName = currentCategoryName();
            const priority = prioritySelect.value || 'essential';

            if (!expenseName) {
                setFeedback('ကုန်ကျစရိတ်အမည်ကို အရင်ထည့်ပါ။', 'error');
                nameInput.focus();
                return;
            }

            if (!(amount > 0)) {
                setFeedback('Amount ကို 0 ထက်ကြီးတဲ့ ပမာဏထည့်ပါ။', 'error');
                amountInput.focus();
                return;
            }

            if (!categoryName) {
                setFeedback('Category ကို ရွေးပါ သို့မဟုတ် Custom Category အမည်ထည့်ပါ။', 'error');
                (categorySelect.value === '__custom__' ? customInput : categorySelect).focus();
                return;
            }

            const category = ensureCategory(categoryName);
            const item = ensureItem(category);
            if (!item) return;

            const itemName = item.querySelector('[data-item-name]');
            const itemAmount = item.querySelector('[data-item-amount]');
            const itemPriority = item.querySelector('[data-item-priority]');

            itemName.value = expenseName;
            itemAmount.value = String(amount);
            itemPriority.value = priority;

            dispatchInput(itemName);
            dispatchInput(itemAmount);
            dispatchInput(itemPriority);

            item.classList.add('pbr-fast-flash');
            window.setTimeout(() => item.classList.remove('pbr-fast-flash'), 900);

            setFeedback(`✓ ${expenseName} · ${moneyText(amount)} ကို ${categoryName} ထဲ ထည့်ပြီးပါပြီ။`, 'success');

            nameInput.value = '';
            amountInput.value = '';
            nameInput.focus();
        };

        categorySelect.addEventListener('change', () => {
            const custom = categorySelect.value === '__custom__';
            customField.hidden = !custom;
            if (custom) customInput.focus();
        });

        addButton.addEventListener('click', addExpense);

        fastEntry.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            if (event.target.tagName === 'SELECT') return;
            event.preventDefault();
            addExpense();
        });

        /* Turn Quick Start chips into a category picker instead of forcing a
         * new empty category and page jump. */
        builder.addEventListener('click', (event) => {
            const templateButton = event.target.closest('[data-add-template]');
            if (!templateButton) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            const category = (templateButton.dataset.addTemplate || '').trim();
            if (!category) return;

            categorySelect.value = category;
            customField.hidden = true;
            setFeedback(`${category} ကို ရွေးထားပါတယ်။ ကုန်ကျစရိတ်အမည်နဲ့ Amount ကိုထည့်ပါ။`, 'neutral');
            fastEntry.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => nameInput.focus(), 250);
        }, true);

        /* Existing rows keep the same data contract, but the disclosure label
         * becomes easier to understand. */
        builder.querySelectorAll('.pbr-capital-item-details > summary').forEach((summary) => {
            summary.innerHTML = 'Funding & timing ထည့်ရန် <small>Optional details</small><span>＋</span>';
        });

        /* Simplify Working Plans: primary actions stay visible, management
         * actions move into a compact overflow menu. No endpoint is removed. */
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

            if (secondary.length) {
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
            }
        });

        /* Make approval visible as a product state instead of only a flash
         * message. Existing approved result values are reused from the page. */
        const activeRuleText = page.querySelector('.pbr-capital-business-card b')?.textContent || '';
        if (/Active Rule/i.test(activeRuleText)) {
            const workflow = page.querySelector('.pbr-capital-tool-flow');
            const total = builder.querySelector('[data-live-total]')?.textContent?.trim() || `${currency} 0.00`;
            const funded = builder.querySelector('[data-live-funded]')?.textContent?.trim() || `${currency} 0.00`;
            const gap = builder.querySelector('[data-live-gap]')?.textContent?.trim() || `${currency} 0.00`;
            const revision = activeRuleText.match(/Rev\s*(\d+)/i)?.[1] || '';
            const workspaceMatch = window.location.pathname.match(/\/workspaces\/(\d+)\//);
            const workspaceId = workspaceMatch?.[1];

            const banner = document.createElement('section');
            banner.className = 'pbr-current-rule-banner';
            banner.innerHTML = `
                <div class="pbr-current-rule-copy">
                    <span class="pbr-current-rule-status">✓ CURRENT BUSINESS RULE</span>
                    <h2>Startup Capital Plan ကို အတည်ပြုပြီး အသုံးပြုနေသည်</h2>
                    <p>ဒီ Plan က PBR Business Operating System အတွက် လက်ရှိ approved Startup Capital position ဖြစ်ပါတယ်${revision ? ` · Revision ${escapeHtml(revision)}` : ''}။ နောက်ထပ်ပြောင်းလဲချင်ရင် Working Plan အသစ်အဖြစ် စတင်ပါ။</p>
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
                fastEntry.scrollIntoView({ behavior: 'smooth', block: 'center' });
                window.setTimeout(() => nameInput.focus(), 250);
            });
        }
    });
})();
