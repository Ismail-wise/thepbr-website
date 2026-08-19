document.addEventListener('DOMContentLoaded', () => {
    const moneyFormatter = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const amount = value => {
        const number = Number(value);

        if (!Number.isFinite(number) || number < 0) {
            return 0;
        }

        return number;
    };

    /*
    |--------------------------------------------------------------------------
    | Custom Category + Item Builders
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-category-builder]')
        .forEach(builder => {
            const field = builder.dataset.field;
            const currency =
                builder.dataset.currency || 'THB';

            let categorySeed =
                Number(builder.dataset.nextIndex || 100);

            let itemSeed = 1000;

            const categories =
                builder.querySelector('[data-categories]');

            const itemHtml = categoryIndex => {
                const itemIndex = itemSeed++;

                return `
                    <div
                        class="pbr-dynamic-item"
                        data-item
                    >
                        <input
                            id="${field}_item_${categoryIndex}_${itemIndex}_name"
                            type="text"
                            name="${field}[${categoryIndex}][items][${itemIndex}][name]"
                            placeholder="Item name"
                            maxlength="150"
                            aria-label="Item name"
                        >

                        <div class="pbr-money-input">
                            <span>${currency}</span>

                            <input
                                id="${field}_item_${categoryIndex}_${itemIndex}_amount"
                                type="number"
                                name="${field}[${categoryIndex}][items][${itemIndex}][amount]"
                                min="0"
                                max="999999999999.99"
                                step="0.01"
                                placeholder="0.00"
                                data-item-amount
                                aria-label="Item amount"
                            >
                        </div>

                        <button
                            type="button"
                            class="pbr-remove-item"
                            data-remove-item
                            aria-label="Remove item"
                        >
                            ×
                        </button>
                    </div>
                `;
            };

            const categoryHtml = (
                presetName = ''
            ) => {
                const index = categorySeed++;

                const safePreset =
                    String(presetName)
                        .replaceAll('&', '&amp;')
                        .replaceAll('"', '&quot;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;');

                return `
                    <section
                        class="pbr-dynamic-category"
                        data-category
                        data-category-index="${index}"
                    >
                        <div class="pbr-category-head">

                            <div class="pbr-category-name-wrap">
                                <label>
                                    Category Name
                                </label>

                                <input
                                    id="${field}_category_${index}"
                                    type="text"
                                    name="${field}[${index}][name]"
                                    placeholder="Type your category name"
                                    maxlength="120"
                                    value="${safePreset}"
                                    required
                                >
                            </div>

                            <button
                                type="button"
                                class="pbr-remove-category"
                                data-remove-category
                                aria-label="Remove category"
                            >
                                Remove Category
                            </button>

                        </div>

                        <div
                            class="pbr-category-items"
                            data-items
                        >
                            ${itemHtml(index)}
                        </div>

                        <div class="pbr-category-footer">

                            <button
                                type="button"
                                class="pbr-add-item"
                                data-add-item
                            >
                                + Add Item
                            </button>

                            <div>
                                <span>
                                    Category Subtotal
                                </span>

                                <strong data-category-subtotal aria-live="polite">
                                    ${currency} 0.00
                                </strong>
                            </div>

                        </div>
                    </section>
                `;
            };

            const updateCategorySubtotal = category => {
                let subtotal = 0;

                category
                    .querySelectorAll('[data-item-amount]')
                    .forEach(input => {
                        subtotal += amount(input.value);
                    });

                const target = category.querySelector(
                    '[data-category-subtotal]'
                );

                if (target) {
                    target.textContent =
                        `${currency} ${moneyFormatter.format(subtotal)}`;
                }
            };

            const updateAll = () => {
                builder
                    .querySelectorAll('[data-category]')
                    .forEach(updateCategorySubtotal);
            };

            builder.addEventListener('click', event => {
                const addCategory =
                    event.target.closest('[data-add-category]');

                if (addCategory) {
                    categories.insertAdjacentHTML(
                        'beforeend',
                        categoryHtml()
                    );

                    updateAll();
                    categories.lastElementChild
                        ?.querySelector('input[type="text"]')
                        ?.focus();
                    return;
                }

                const presetCategory =
                    event.target.closest(
                        '[data-add-category-preset]'
                    );

                if (presetCategory) {
                    const name =
                        presetCategory.dataset
                            .addCategoryPreset || '';

                    categories.insertAdjacentHTML(
                        'beforeend',
                        categoryHtml(name)
                    );

                    updateAll();

                    presetCategory.disabled = true;
                    presetCategory.setAttribute(
                        'aria-pressed',
                        'true'
                    );
                    presetCategory.classList.add(
                        'is-added'
                    );

                    return;
                }

                const addItem =
                    event.target.closest('[data-add-item]');

                if (addItem) {
                    const category =
                        addItem.closest('[data-category]');

                    const index =
                        category.dataset.categoryIndex;

                    category
                        .querySelector('[data-items]')
                        .insertAdjacentHTML(
                            'beforeend',
                            itemHtml(index)
                        );

                    updateAll();
                    category
                        .querySelector('[data-items]')
                        .lastElementChild
                        ?.querySelector('input[type="text"]')
                        ?.focus();
                    return;
                }

                const removeItem =
                    event.target.closest('[data-remove-item]');

                if (removeItem) {
                    const category =
                        removeItem.closest('[data-category]');

                    removeItem
                        .closest('[data-item]')
                        ?.remove();

                    if (category) {
                        updateCategorySubtotal(category);
                    }

                    return;
                }

                const removeCategory =
                    event.target.closest(
                        '[data-remove-category]'
                    );

                if (removeCategory) {
                    const category =
                        removeCategory.closest(
                            '[data-category]'
                        );

                    if (
                        category
                        && !category.hasAttribute(
                            'data-locked-category'
                        )
                    ) {
                        category.remove();
                    }
                }
            });

            builder.addEventListener(
                'input',
                event => {
                    if (
                        event.target.matches(
                            '[data-item-amount]'
                        )
                    ) {
                        const category =
                            event.target.closest(
                                '[data-category]'
                            );

                        if (category) {
                            updateCategorySubtotal(
                                category
                            );
                        }
                    }
                }
            );

            updateAll();
        });


    /*
    |--------------------------------------------------------------------------
    | Partner Contribution Matrix
    |--------------------------------------------------------------------------
    */

    const partnerBuilder =
        document.querySelector('[data-partner-builder]');

    if (partnerBuilder) {
        const currency =
            partnerBuilder.dataset.currency || 'THB';

        let partnerSeed =
            Number(
                partnerBuilder.dataset.nextPartner
                || 100
            );

        let contributionSeed = 1000;

        const partners =
            partnerBuilder.querySelector('[data-partners]');

        const contributionHtml = partnerIndex => {
            const contributionIndex =
                contributionSeed++;

            return `
                <div
                    class="pbr-dynamic-item"
                    data-contribution
                >
                    <input
                        type="text"
                        name="partners[${partnerIndex}][contributions][${contributionIndex}][name]"
                        placeholder="Contribution name"
                        maxlength="150"
                        aria-label="Contribution name for partner ${partnerIndex + 1}"
                    >

                    <div class="pbr-money-input">
                        <span>${currency}</span>

                        <input
                            type="number"
                            name="partners[${partnerIndex}][contributions][${contributionIndex}][amount]"
                            min="0"
                            max="999999999999.99"
                            step="0.01"
                            placeholder="0.00"
                            data-contribution-amount
                            aria-label="Contribution amount for partner ${partnerIndex + 1}"
                        >
                    </div>

                    <button
                        type="button"
                        class="pbr-remove-item"
                        data-remove-contribution
                        aria-label="Remove contribution for partner ${partnerIndex + 1}"
                    >
                        ×
                    </button>
                </div>
            `;
        };

        const partnerHtml = () => {
            const partnerIndex = partnerSeed++;

            return `
                <section
                    class="pbr-partner-card"
                    data-partner
                    data-partner-index="${partnerIndex}"
                >
                    <div class="pbr-category-head">

                        <div class="pbr-category-name-wrap">
                            <label>
                                Partner Name
                            </label>

                            <input
                                type="text"
                                name="partners[${partnerIndex}][name]"
                                placeholder="Partner name"
                                maxlength="120"
                                aria-label="Partner ${partnerIndex + 1} name"
                            >
                        </div>

                        <button
                            type="button"
                            class="pbr-remove-category"
                            data-remove-partner
                            aria-label="Remove partner ${partnerIndex + 1}"
                        >
                            Remove Partner
                        </button>

                    </div>

                    <div data-contributions>
                        ${contributionHtml(partnerIndex)}
                    </div>

                    <div class="pbr-category-footer">

                        <button
                            type="button"
                            class="pbr-add-item"
                            data-add-contribution
                        >
                            + Add Contribution
                        </button>

                        <div>
                            <span>Partner Total</span>

                            <strong data-partner-total aria-live="polite">
                                ${currency} 0.00
                            </strong>
                        </div>

                    </div>
                </section>
            `;
        };

        const updatePartner = partner => {
            let total = 0;

            partner
                .querySelectorAll(
                    '[data-contribution-amount]'
                )
                .forEach(input => {
                    total += amount(input.value);
                });

            const target =
                partner.querySelector(
                    '[data-partner-total]'
                );

            if (target) {
                target.textContent =
                    `${currency} ${moneyFormatter.format(total)}`;
            }

            return total;
        };

        const updatePartners = () => {
            const partnerCards = [
                ...partnerBuilder.querySelectorAll(
                    '[data-partner]'
                ),
            ];

            const grandTotal = partnerCards.reduce(
                (sum, partner) => sum + updatePartner(partner),
                0
            );

            const countTarget = partnerBuilder.querySelector(
                '[data-partner-count]'
            );

            const totalTarget = partnerBuilder.querySelector(
                '[data-partner-grand-total]'
            );

            if (countTarget) {
                countTarget.textContent = partnerCards.length;
            }

            if (totalTarget) {
                totalTarget.textContent =
                    `${currency} ${moneyFormatter.format(grandTotal)}`;
            }
        };

        partnerBuilder.addEventListener(
            'click',
            event => {
                if (
                    event.target.closest(
                        '[data-add-partner]'
                    )
                ) {
                    partners.insertAdjacentHTML(
                        'beforeend',
                        partnerHtml()
                    );

                    updatePartners();
                    partners.lastElementChild
                        ?.querySelector('input[type="text"]')
                        ?.focus();
                    return;
                }

                const addContribution =
                    event.target.closest(
                        '[data-add-contribution]'
                    );

                if (addContribution) {
                    const partner =
                        addContribution.closest(
                            '[data-partner]'
                        );

                    const partnerIndex =
                        partner.dataset.partnerIndex;

                    partner
                        .querySelector(
                            '[data-contributions]'
                        )
                        .insertAdjacentHTML(
                            'beforeend',
                            contributionHtml(
                                partnerIndex
                            )
                        );

                    updatePartners();
                    partner
                        .querySelector('[data-contributions]')
                        .lastElementChild
                        ?.querySelector('input[type="text"]')
                        ?.focus();
                    return;
                }

                const removeContribution =
                    event.target.closest(
                        '[data-remove-contribution]'
                    );

                if (removeContribution) {
                    const partner =
                        removeContribution.closest(
                            '[data-partner]'
                        );

                    removeContribution
                        .closest(
                            '[data-contribution]'
                        )
                        ?.remove();

                    if (partner) {
                        updatePartners();
                    }

                    return;
                }

                const removePartner =
                    event.target.closest(
                        '[data-remove-partner]'
                    );

                if (removePartner) {
                    removePartner
                        .closest('[data-partner]')
                        ?.remove();

                    updatePartners();
                }
            }
        );

        partnerBuilder.addEventListener(
            'input',
            event => {
                if (
                    event.target.matches(
                        '[data-contribution-amount]'
                    )
                ) {
                    const partner =
                        event.target.closest(
                            '[data-partner]'
                        );

                    if (partner) {
                        updatePartners();
                    }
                }
            }
        );

        updatePartners();
    }


    /*
    |--------------------------------------------------------------------------
    | Capital Allocation Builder
    |--------------------------------------------------------------------------
    */

    const allocationBuilder =
        document.querySelector(
            '[data-allocation-builder]'
        );

    if (allocationBuilder) {
        const currency =
            allocationBuilder.dataset.currency
            || 'THB';

        let allocationSeed =
            Number(
                allocationBuilder.dataset
                    .nextAllocation
                || 100
            );

        const allocations =
            allocationBuilder.querySelector(
                '[data-allocations]'
            );

        const allocationHtml = (presetName = '') => {
            const index = allocationSeed++;

            const safePreset = String(presetName)
                .replaceAll('&', '&amp;')
                .replaceAll('"', '&quot;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');

            return `
                <div
                    class="pbr-dynamic-item"
                    data-allocation
                >
                    <input
                        type="text"
                        name="allocations[${index}][name]"
                        placeholder="Capital use"
                        maxlength="150"
                        value="${safePreset}"
                        aria-label="Capital use ${index + 1} name"
                    >

                    <div class="pbr-money-input">
                        <span>${currency}</span>

                        <input
                            type="number"
                            name="allocations[${index}][amount]"
                            min="0"
                            max="999999999999.99"
                            step="0.01"
                            placeholder="0.00"
                            data-allocation-amount
                            aria-label="Capital use ${index + 1} amount"
                        >
                    </div>

                    <button
                        type="button"
                        class="pbr-remove-item"
                        data-remove-allocation
                        aria-label="Remove capital use ${index + 1}"
                    >
                        ×
                    </button>
                </div>
            `;
        };

        const updateAllocations = () => {
            const rows = [
                ...allocationBuilder.querySelectorAll(
                    '[data-allocation]'
                ),
            ];

            const total = rows.reduce((sum, row) => {
                const input = row.querySelector(
                    '[data-allocation-amount]'
                );

                return sum + amount(input?.value);
            }, 0);

            const countTarget = allocationBuilder.querySelector(
                '[data-allocation-count]'
            );

            const totalTarget = allocationBuilder.querySelector(
                '[data-allocation-total]'
            );

            if (countTarget) {
                countTarget.textContent = rows.length;
            }

            if (totalTarget) {
                totalTarget.textContent =
                    `${currency} ${moneyFormatter.format(total)}`;
            }
        };

        allocationBuilder.addEventListener(
            'click',
            event => {
                if (
                    event.target.closest(
                        '[data-add-allocation]'
                    )
                ) {
                    allocations.insertAdjacentHTML(
                        'beforeend',
                        allocationHtml()
                    );

                    updateAllocations();
                    allocations.lastElementChild
                        ?.querySelector('input[type="text"]')
                        ?.focus();
                    return;
                }

                const preset = event.target.closest(
                    '[data-add-allocation-preset]'
                );

                if (preset) {
                    allocations.insertAdjacentHTML(
                        'beforeend',
                        allocationHtml(
                            preset.dataset.addAllocationPreset
                            || ''
                        )
                    );

                    preset.disabled = true;
                    preset.setAttribute('aria-pressed', 'true');
                    preset.classList.add('is-added');
                    updateAllocations();
                    allocations.lastElementChild
                        ?.querySelector('[data-allocation-amount]')
                        ?.focus();
                    return;
                }

                const remove =
                    event.target.closest(
                        '[data-remove-allocation]'
                    );

                if (remove) {
                    remove
                        .closest('[data-allocation]')
                        ?.remove();

                    updateAllocations();
                }
            }
        );

        allocationBuilder.addEventListener(
            'input',
            event => {
                if (event.target.matches('[data-allocation-amount]')) {
                    updateAllocations();
                }
            }
        );

        updateAllocations();
    }


    /*
    |--------------------------------------------------------------------------
    | Contingency Method Switch
    |--------------------------------------------------------------------------
    */

    const contingency =
        document.querySelector(
            '[data-contingency-tool]'
        );

    if (contingency) {
        const select =
            contingency.querySelector(
                '[data-contingency-method]'
            );

        const percentageFields =
            contingency.querySelector(
                '[data-percentage-fields]'
            );

        const monthFields =
            contingency.querySelector(
                '[data-month-fields]'
            );

        const methodHelp =
            contingency.querySelector(
                '[data-contingency-method-help]'
            );

        const percentageInput =
            contingency.querySelector('#percentage');

        const monthsInput =
            contingency.querySelector('#months');

        const updatePresetState = (
            selector,
            input,
            dataKey
        ) => {
            contingency
                .querySelectorAll(selector)
                .forEach(button => {
                    const isActive =
                        Number(button.dataset[dataKey])
                        === Number(input?.value);

                    button.classList.toggle(
                        'is-active',
                        isActive
                    );

                    button.setAttribute(
                        'aria-pressed',
                        isActive ? 'true' : 'false'
                    );
                });
        };

        const updateMethod = () => {
            const method =
                select?.value || 'percentage';

            if (percentageFields) {
                percentageFields.hidden =
                    method !== 'percentage';

                percentageFields.setAttribute(
                    'aria-hidden',
                    method === 'percentage'
                        ? 'false'
                        : 'true'
                );
            }

            if (monthFields) {
                monthFields.hidden =
                    method !== 'months';

                monthFields.setAttribute(
                    'aria-hidden',
                    method === 'months'
                        ? 'false'
                        : 'true'
                );
            }

            if (methodHelp) {
                methodHelp.textContent =
                    method === 'months'
                        ? 'Operating Months method က monthly operating cost ကို reserve months နဲ့မြှောက်ပြီး buffer ကိုတွက်ပေးပါတယ်။'
                        : 'Percentage method က base capital ပေါ်မူတည်ပြီး reserve ကိုတွက်ပေးပါတယ်။';
            }
        };

        contingency.addEventListener('click', event => {
            const percentagePreset = event.target.closest(
                '[data-contingency-percentage]'
            );

            if (percentagePreset && percentageInput) {
                percentageInput.value =
                    percentagePreset.dataset
                        .contingencyPercentage || '';

                percentageInput.dispatchEvent(
                    new Event('input', { bubbles: true })
                );

                updatePresetState(
                    '[data-contingency-percentage]',
                    percentageInput,
                    'contingencyPercentage'
                );

                return;
            }

            const monthsPreset = event.target.closest(
                '[data-contingency-months]'
            );

            if (monthsPreset && monthsInput) {
                monthsInput.value =
                    monthsPreset.dataset
                        .contingencyMonths || '';

                monthsInput.dispatchEvent(
                    new Event('input', { bubbles: true })
                );

                updatePresetState(
                    '[data-contingency-months]',
                    monthsInput,
                    'contingencyMonths'
                );
            }
        });

        percentageInput?.addEventListener('input', () => {
            updatePresetState(
                '[data-contingency-percentage]',
                percentageInput,
                'contingencyPercentage'
            );
        });

        monthsInput?.addEventListener('input', () => {
            updatePresetState(
                '[data-contingency-months]',
                monthsInput,
                'contingencyMonths'
            );
        });

        select?.addEventListener(
            'change',
            updateMethod
        );

        updateMethod();

        updatePresetState(
            '[data-contingency-percentage]',
            percentageInput,
            'contingencyPercentage'
        );

        updatePresetState(
            '[data-contingency-months]',
            monthsInput,
            'contingencyMonths'
        );
    }

    /* ---------------------------------------------------------------
       Working Capital Reserve Presets
       --------------------------------------------------------------- */

    const reserveMonthsInput =
        document.querySelector('#reserve_months');

    document
        .querySelectorAll('[data-reserve-months]')
        .forEach(button => {
            button.addEventListener('click', () => {
                if (! reserveMonthsInput) {
                    return;
                }

                reserveMonthsInput.value =
                    button.dataset.reserveMonths || '';

                reserveMonthsInput.dispatchEvent(
                    new Event('input', {
                        bubbles: true,
                    })
                );

                document
                    .querySelectorAll(
                        '[data-reserve-months]'
                    )
                    .forEach(item => {
                        item.classList.toggle(
                            'is-active',
                            item === button
                        );
                    });
            });
        });

});
