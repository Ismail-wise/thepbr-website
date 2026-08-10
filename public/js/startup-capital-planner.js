document.addEventListener('DOMContentLoaded', () => {
    const builder = document.querySelector('#startup-capital-builder');

    if (!builder) {
        return;
    }

    const categories = builder.querySelector('[data-categories]');
    const addCategoryButton = builder.querySelector('[data-add-category]');
    const currency = builder.dataset.currency || 'THB';

    let categorySeed = Number(builder.dataset.nextCategory || 100);
    let itemSeed = 1000;

    const money = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function itemHtml(categoryIndex) {
        const itemIndex = itemSeed++;

        return `
            <div class="pbr-dynamic-item" data-item>
                <input
                    type="text"
                    name="categories[${categoryIndex}][items][${itemIndex}][name]"
                    placeholder="Item name"
                    maxlength="150"
                    data-item-name
                >

                <div class="pbr-money-input">
                    <span>${currency}</span>

                    <input
                        type="number"
                        name="categories[${categoryIndex}][items][${itemIndex}][amount]"
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
    }

    function categoryHtml() {
        const index = categorySeed++;

        return `
            <section
                class="pbr-dynamic-category"
                data-category
                data-category-index="${index}"
            >
                <div class="pbr-category-head">
                    <div class="pbr-category-name-wrap">
                        <label>Category Name</label>

                        <input
                            type="text"
                            name="categories[${index}][name]"
                            placeholder="Type your category name"
                            maxlength="120"
                            data-category-name
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

                <div class="pbr-category-items" data-items>
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
                        <span>Category Subtotal</span>
                        <strong data-category-subtotal>
                            ${currency} 0.00
                        </strong>
                    </div>
                </div>
            </section>
        `;
    }

    function updateTotals() {
        let grandTotal = 0;
        let itemCount = 0;

        builder.querySelectorAll('[data-category]').forEach(category => {
            let subtotal = 0;

            category.querySelectorAll('[data-item-amount]').forEach(input => {
                const amount = Math.max(0, Number(input.value) || 0);

                subtotal += amount;

                if (amount > 0) {
                    itemCount++;
                }
            });

            grandTotal += subtotal;

            const subtotalTarget =
                category.querySelector('[data-category-subtotal]');

            if (subtotalTarget) {
                subtotalTarget.textContent =
                    `${currency} ${money.format(subtotal)}`;
            }
        });

        const totalTarget = builder.querySelector('[data-live-total]');
        const itemTarget = builder.querySelector('[data-live-items]');
        const categoryTarget = builder.querySelector('[data-live-categories]');

        if (totalTarget) {
            totalTarget.textContent =
                `${currency} ${money.format(grandTotal)}`;
        }

        if (itemTarget) {
            itemTarget.textContent = itemCount;
        }

        if (categoryTarget) {
            categoryTarget.textContent =
                builder.querySelectorAll('[data-category]').length;
        }
    }

    addCategoryButton?.addEventListener('click', () => {
        categories.insertAdjacentHTML(
            'beforeend',
            categoryHtml()
        );

        updateTotals();
    });

    builder.addEventListener('click', event => {
        const addItem = event.target.closest('[data-add-item]');

        if (addItem) {
            const category = addItem.closest('[data-category]');
            const index = category.dataset.categoryIndex;
            const items = category.querySelector('[data-items]');

            items.insertAdjacentHTML(
                'beforeend',
                itemHtml(index)
            );

            return;
        }

        const removeItem = event.target.closest('[data-remove-item]');

        if (removeItem) {
            removeItem.closest('[data-item]')?.remove();
            updateTotals();
            return;
        }

        const removeCategory =
            event.target.closest('[data-remove-category]');

        if (removeCategory) {
            removeCategory.closest('[data-category]')?.remove();
            updateTotals();
        }
    });

    builder.addEventListener('input', updateTotals);

    updateTotals();
});
