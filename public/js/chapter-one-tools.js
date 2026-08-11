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
                            type="text"
                            name="${field}[${categoryIndex}][items][${itemIndex}][name]"
                            placeholder="Item name"
                            maxlength="150"
                        >

                        <div class="pbr-money-input">
                            <span>${currency}</span>

                            <input
                                type="number"
                                name="${field}[${categoryIndex}][items][${itemIndex}][amount]"
                                min="0"
                                max="999999999999.99"
                                step="0.01"
                                placeholder="0.00"
                                data-item-amount
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

            const categoryHtml = () => {
                const index = categorySeed++;

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
                                    type="text"
                                    name="${field}[${index}][name]"
                                    placeholder="Type your category name"
                                    maxlength="120"
                                    required
                                >
                            </div>

                            <button
                                type="button"
                                class="pbr-remove-category"
                                data-remove-category
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

                                <strong data-category-subtotal>
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
                        >
                    </div>

                    <button
                        type="button"
                        class="pbr-remove-item"
                        data-remove-contribution
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
                            >
                        </div>

                        <button
                            type="button"
                            class="pbr-remove-category"
                            data-remove-partner
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

                            <strong data-partner-total>
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
        };

        const updatePartners = () => {
            partnerBuilder
                .querySelectorAll('[data-partner]')
                .forEach(updatePartner);
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

                    updatePartner(partner);
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
                        updatePartner(partner);
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
                        updatePartner(partner);
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

        const allocationHtml = () => {
            const index = allocationSeed++;

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
                        >
                    </div>

                    <button
                        type="button"
                        class="pbr-remove-item"
                        data-remove-allocation
                    >
                        ×
                    </button>
                </div>
            `;
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
                }
            }
        );
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

        const updateMethod = () => {
            const method =
                select?.value || 'percentage';

            if (percentageFields) {
                percentageFields.hidden =
                    method !== 'percentage';
            }

            if (monthFields) {
                monthFields.hidden =
                    method !== 'months';
            }
        };

        select?.addEventListener(
            'change',
            updateMethod
        );

        updateMethod();
    }
});
