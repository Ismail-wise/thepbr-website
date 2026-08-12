document.addEventListener('DOMContentLoaded', () => {
    const builder = document.querySelector('#startup-capital-builder');
    if (!builder) return;

    const categoriesRoot = builder.querySelector('[data-categories]');
    const emptyState = builder.querySelector('[data-capital-empty]');
    const currency = builder.dataset.currency || 'THB';
    let categorySeed = Number(builder.dataset.nextCategory || 100);
    let itemSeed = 1000;

    const money = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const moneyText = value => `${currency} ${money.format(Math.max(0, Number(value) || 0))}`;

    function itemHtml(categoryIndex) {
        const itemIndex = itemSeed++;
        return `
            <article class="pbr-capital-item" data-item>
                <div class="pbr-capital-item-main">
                    <div class="pbr-capital-field pbr-capital-item-name">
                        <label>ကုန်ကျစရိတ်</label>
                        <input type="text" name="categories[${categoryIndex}][items][${itemIndex}][name]" placeholder="ဥပမာ: ဆိုင် Deposit" maxlength="150" data-item-name>
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
                    <summary>Funding, timing နဲ့ အသေးစိတ် ထည့်ရန် <span>＋</span></summary>
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
    }

    function categoryHtml(name = '') {
        const index = categorySeed++;
        return `
            <section class="pbr-capital-category" data-category data-category-index="${index}">
                <header class="pbr-capital-category-head">
                    <div class="pbr-capital-category-name">
                        <span>ကုန်ကျစရိတ်အုပ်စု</span>
                        <input type="text" name="categories[${index}][name]" value="${escapeHtml(name)}" placeholder="ဥပမာ: ဆိုင်ပြင်ဆင်မှု" maxlength="120" required data-category-name>
                    </div>
                    <div class="pbr-capital-category-meta">
                        <div><small>Planned</small><strong data-category-subtotal>${escapeHtml(currency)} 0.00</strong></div>
                        <div><small>Funded</small><strong data-category-funded>${escapeHtml(currency)} 0.00</strong></div>
                        <button type="button" data-remove-category aria-label="ဒီအုပ်စုကို ဖယ်ရန်">×</button>
                    </div>
                </header>
                <div class="pbr-capital-items" data-items>${itemHtml(index)}</div>
                <footer class="pbr-capital-category-footer">
                    <button type="button" data-add-item>+ ကုန်ကျစရိတ်ထည့်ရန်</button>
                    <span>Item <b data-category-item-count>1</b> ခု</span>
                </footer>
            </section>
        `;
    }

    function itemValues(item) {
        const amount = Math.max(0, Number(item.querySelector('[data-item-amount]')?.value) || 0);
        const frequency = item.querySelector('[data-item-frequency]')?.value || 'one_time';
        const monthsInput = item.querySelector('[data-item-months]');
        const months = frequency === 'monthly'
            ? Math.max(1, Math.min(24, Number(monthsInput?.value) || 3))
            : 1;
        const planned = amount * months;
        const rawFunded = Math.max(0, Number(item.querySelector('[data-item-funded]')?.value) || 0);
        const funded = Math.min(planned, rawFunded);
        const outstanding = Math.max(0, planned - funded);
        const priority = item.querySelector('[data-item-priority]')?.value || 'essential';
        const due = item.querySelector('[data-item-due]')?.value || '';

        const monthsField = item.querySelector('[data-months-field]');
        if (monthsField) monthsField.hidden = frequency !== 'monthly';

        const planLabel = item.querySelector('[data-item-plan-label]');
        if (planLabel) {
            planLabel.textContent = frequency === 'monthly'
                ? `လစဉ် ${moneyText(amount)} × ${months} လ = ${moneyText(planned)}`
                : `Plan ထဲတွင် ${moneyText(planned)}`;
        }

        const fundingLabel = item.querySelector('[data-item-funding-label]');
        if (fundingLabel) {
            if (planned <= 0) {
                fundingLabel.textContent = 'Amount မထည့်ရသေး';
                fundingLabel.className = '';
            } else if (funded >= planned) {
                fundingLabel.textContent = '✓ Funding ပြည့်';
                fundingLabel.className = 'is-funded';
            } else if (funded > 0) {
                fundingLabel.textContent = `${moneyText(outstanding)} လိုနေသေး`;
                fundingLabel.className = 'is-partial';
            } else {
                fundingLabel.textContent = 'Funding မရသေး';
                fundingLabel.className = 'is-unfunded';
            }
        }

        return { amount, frequency, months, planned, funded, outstanding, priority, due };
    }

    function dueBucket(due, outstanding) {
        if (!due || outstanding <= 0) return { due30: 0, overdue: 0 };
        const date = new Date(`${due}T00:00:00`);
        if (Number.isNaN(date.getTime())) return { due30: 0, overdue: 0 };

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const cutoff = new Date(today);
        cutoff.setDate(cutoff.getDate() + 30);

        if (date < today) return { due30: 0, overdue: outstanding };
        if (date <= cutoff) return { due30: outstanding, overdue: 0 };
        return { due30: 0, overdue: 0 };
    }

    function setMoney(selector, value) {
        const target = builder.querySelector(selector);
        if (target) target.textContent = moneyText(value);
    }

    function updateTotals() {
        let total = 0;
        let essential = 0;
        let optional = 0;
        let funded = 0;
        let essentialFunded = 0;
        let due30 = 0;
        let overdue = 0;
        let monthly = 0;

        builder.querySelectorAll('[data-category]').forEach(category => {
            let categoryTotal = 0;
            let categoryFunded = 0;
            let itemCount = 0;

            category.querySelectorAll('[data-item]').forEach(item => {
                const values = itemValues(item);
                categoryTotal += values.planned;
                categoryFunded += values.funded;

                if (values.planned > 0) itemCount++;
                if (values.priority === 'essential') {
                    essential += values.planned;
                    essentialFunded += values.funded;
                } else {
                    optional += values.planned;
                }
                if (values.frequency === 'monthly') monthly += values.amount;

                const due = dueBucket(values.due, values.outstanding);
                due30 += due.due30;
                overdue += due.overdue;
            });

            total += categoryTotal;
            funded += categoryFunded;

            const subtotalTarget = category.querySelector('[data-category-subtotal]');
            const fundedTarget = category.querySelector('[data-category-funded]');
            const countTarget = category.querySelector('[data-category-item-count]');
            if (subtotalTarget) subtotalTarget.textContent = moneyText(categoryTotal);
            if (fundedTarget) fundedTarget.textContent = moneyText(categoryFunded);
            if (countTarget) countTarget.textContent = String(category.querySelectorAll('[data-item]').length);
        });

        funded = Math.min(total, funded);
        const gap = Math.max(0, total - funded);
        const essentialGap = Math.max(0, essential - essentialFunded);
        const percent = total > 0 ? Math.min(100, (funded / total) * 100) : 0;

        setMoney('[data-live-total]', total);
        setMoney('[data-live-essential]', essential);
        setMoney('[data-live-optional]', optional);
        setMoney('[data-live-funded]', funded);
        setMoney('[data-live-gap]', gap);
        setMoney('[data-live-due30]', due30);
        setMoney('[data-live-monthly]', monthly);

        const progress = builder.querySelector('[data-funding-progress]');
        if (progress) progress.style.width = `${percent}%`;
        const percentTarget = builder.querySelector('[data-live-funded-percent]');
        if (percentTarget) percentTarget.textContent = `${Math.round(percent)}%`;

        const alert = builder.querySelector('[data-live-alert]');
        if (alert) {
            alert.classList.remove('warning', 'healthy', 'neutral', 'danger');
            if (overdue > 0) {
                alert.classList.add('danger');
                alert.innerHTML = `<strong>Due Date ကျော်နေသော Funding လိုအပ်ချက်ရှိသည်</strong><p>${moneyText(overdue)} ကို အမြန်ပြန်စစ်ပါ။</p>`;
            } else if (due30 > 0) {
                alert.classList.add('warning');
                alert.innerHTML = `<strong>နောက် 30 ရက်အတွင်း Funding လိုမည်</strong><p>${moneyText(due30)} outstanding ရှိနေပါတယ်။</p>`;
            } else if (essentialGap > 0) {
                alert.classList.add('warning');
                alert.innerHTML = `<strong>Essential Costs အတွက် Funding မပြည့်သေးပါ</strong><p>${moneyText(essentialGap)} ထပ်မံသတ်မှတ်ရန် လိုပါတယ်။</p>`;
            } else if (gap > 0) {
                alert.classList.add('warning');
                alert.innerHTML = `<strong>Funding Gap ရှိနေသည်</strong><p>${moneyText(gap)} ထပ်မံရှာဖွေ/သတ်မှတ်ရန် လိုပါတယ်။</p>`;
            } else if (total > 0) {
                alert.classList.add('healthy');
                alert.innerHTML = '<strong>Plan Funding ပြည့်စုံနေသည်</strong><p>လက်ရှိထည့်ထားတဲ့ Plan အရ Funding Gap မရှိပါ။</p>';
            } else {
                alert.classList.add('neutral');
                alert.innerHTML = '<strong>Plan စတင်တည်ဆောက်ပါ</strong><p>ကုန်ကျစရိတ်ထည့်တာနဲ့ ဒီနေရာမှာ Funding Position ကို live မြင်ရပါမယ်။</p>';
            }
        }

        if (emptyState) {
            emptyState.classList.toggle('is-hidden', builder.querySelectorAll('[data-category]').length > 0);
        }
    }

    function addCategory(name = '') {
        if (!categoriesRoot) return;
        categoriesRoot.insertAdjacentHTML('beforeend', categoryHtml(name));
        const added = categoriesRoot.lastElementChild;
        added?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const firstInput = added?.querySelector('[data-category-name]');
        if (!name) firstInput?.focus();
        updateTotals();
    }

    builder.addEventListener('click', event => {
        const templateButton = event.target.closest('[data-add-template]');
        if (templateButton) {
            const name = templateButton.dataset.addTemplate || '';
            const existing = [...builder.querySelectorAll('[data-category-name]')]
                .find(input => input.value.trim().toLowerCase() === name.trim().toLowerCase());
            if (existing) {
                existing.closest('[data-category]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                existing.focus();
            } else {
                addCategory(name);
            }
            return;
        }

        if (event.target.closest('[data-add-category]')) {
            addCategory('');
            return;
        }

        const addItemButton = event.target.closest('[data-add-item]');
        if (addItemButton) {
            const category = addItemButton.closest('[data-category]');
            const items = category?.querySelector('[data-items]');
            const index = category?.dataset.categoryIndex;
            if (items && index !== undefined) {
                items.insertAdjacentHTML('beforeend', itemHtml(index));
                items.lastElementChild?.querySelector('[data-item-name]')?.focus();
                updateTotals();
            }
            return;
        }

        const removeItem = event.target.closest('[data-remove-item]');
        if (removeItem) {
            removeItem.closest('[data-item]')?.remove();
            updateTotals();
            return;
        }

        const removeCategory = event.target.closest('[data-remove-category]');
        if (removeCategory) {
            const category = removeCategory.closest('[data-category]');
            const hasValues = [...(category?.querySelectorAll('input') || [])]
                .some(input => String(input.value || '').trim() !== '');
            if (!hasValues || window.confirm('ဒီကုန်ကျစရိတ်အုပ်စုကို ဖယ်မလား?')) {
                category?.remove();
                updateTotals();
            }
        }
    });

    builder.addEventListener('input', event => {
        if (event.target.matches('#scenario_name')) {
            event.target.setCustomValidity('');
        }
        updateTotals();
    });
    builder.addEventListener('change', updateTotals);

    builder.addEventListener('submit', event => {
        const submitter = event.submitter;
        const action = submitter?.getAttribute('formaction') || '';
        if (!action.includes('draft')) return;

        const name = builder.querySelector('#scenario_name');
        if (name && name.value.trim() === '') {
            event.preventDefault();
            name.setCustomValidity('Draft သိမ်းရန် Plan အမည်ထည့်ပါ။');
            name.reportValidity();
            name.focus();
        }
    });

    updateTotals();
});
