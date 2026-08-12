(() => {
    'use strict';

    function refreshNumbers(repeater) {
        repeater.querySelectorAll('[data-repeater-row]').forEach((row, index) => {
            const badge = row.querySelector('.pbr-os-row-number');
            if (badge) badge.textContent = String(index + 1);
        });
    }

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
                'ဒီ Scenario ကို Agreed Business Rule အဖြစ် အတည်ပြုမလား?\n\n' +
                'အတည်ပြုပြီးရင် နောက် Chapter တွေနဲ့ PBR AI Advisor က current business rule အဖြစ်အသုံးပြုနိုင်ပါမယ်။'
            );

            if (!accepted) event.preventDefault();
        });
    });
})();
