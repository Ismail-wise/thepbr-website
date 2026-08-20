/**
 * PBR — Unsaved Changes Guard
 *
 * Warns an owner before they leave a tool page with un-submitted input.
 *
 * Why this exists: every PBR tool calculates and saves via a full form POST.
 * Until that POST happens, everything typed lives only in the DOM. A stray
 * click on the sidebar, the browser back button, or a closed tab silently
 * discards a part-filled contribution matrix with no warning. That is real
 * business data loss, not a cosmetic issue.
 *
 * Design constraints deliberately respected here:
 *   - Submitting any form is an intentional exit -> never warn.
 *   - The guard must never block or delay a submit; it only observes.
 *   - Approve/agree flows keep their own confirm() in pbr-operating-system.js.
 *     This file does not touch them.
 *   - Self-contained IIFE, no dependencies, safe to load on any page. If no
 *     tracked form is present it does nothing at all.
 */
(() => {
    'use strict';

    // Forms whose input represents unsaved business data.
    // Verified against the Blade templates:
    //   operating-tool.blade.php   -> id="pbrOperatingToolForm"
    //   chapter-one.blade.php      -> id="chapter-one-tool-form"
    //   startup-capital.blade.php  -> id="startup-capital-builder"
    // data-pbr-guard is the opt-in hook for any future tool form.
    const TRACKED_FORMS = [
        '#pbrOperatingToolForm',
        '#chapter-one-tool-form',
        '#startup-capital-builder',
        '[data-pbr-guard]',
    ].join(',');

    // Never treat these as user-entered content.
    const IGNORED_INPUTS = [
        'hidden',
        'submit',
        'button',
        'reset',
        'image',
        'file',
    ];

    const WARNING_MM =
        'သိမ်းဆည်းမထားသော အချက်အလက်များ ရှိနေပါသည်။ ' +
        'ဤစာမျက်နှာမှ ထွက်ပါက ထည့်သွင်းထားသည်များ ပျောက်ဆုံးပါမည်။';

    let isSubmitting = false;
    const baseline = new WeakMap();

    /**
     * Serialise a form's user-editable state into a comparable string.
     * Used instead of listening for 'input' events so that programmatic
     * changes (repeater rows, prefills) don't produce false positives —
     * we compare against the snapshot taken after load settles.
     */
    function serialise(form) {
        const parts = [];

        form
            .querySelectorAll('input, select, textarea')
            .forEach((el) => {
                if (el.disabled) return;
                if (IGNORED_INPUTS.includes((el.type || '').toLowerCase())) return;
                if (!el.name) return;

                if (el.type === 'checkbox' || el.type === 'radio') {
                    parts.push(`${el.name}=${el.checked ? 1 : 0}`);
                } else {
                    parts.push(`${el.name}=${el.value}`);
                }
            });

        return parts.join('\u0001');
    }

    function trackedForms() {
        return Array.from(document.querySelectorAll(TRACKED_FORMS));
    }

    function captureBaseline() {
        trackedForms().forEach((form) => {
            baseline.set(form, serialise(form));

            // Any submit — calculate, save draft, approve — is intentional.
            form.addEventListener('submit', () => {
                isSubmitting = true;
            });
        });
    }

    function hasUnsavedChanges() {
        return trackedForms().some((form) => {
            const original = baseline.get(form);
            if (original === undefined) return false;
            return serialise(form) !== original;
        });
    }

    function init() {
        if (trackedForms().length === 0) return;

        // Let prefills / repeater initialisation finish before snapshotting,
        // otherwise a server-side prefill reads as a user edit.
        window.setTimeout(captureBaseline, 400);

        window.addEventListener('beforeunload', (event) => {
            if (isSubmitting) return;
            if (!hasUnsavedChanges()) return;

            // Browsers show their own generic wording; returnValue must be
            // set for the prompt to appear at all. WARNING_MM is kept for
            // in-app use (see confirmNavigation below).
            event.preventDefault();
            event.returnValue = WARNING_MM;
            return WARNING_MM;
        });

        // Same-origin link clicks bypass beforeunload in some mobile browsers,
        // so guard in-app navigation explicitly with readable Burmese copy.
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link) return;
            if (link.target === '_blank') return;
            if (link.hasAttribute('data-allow-unsaved')) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

            if (!hasUnsavedChanges()) return;

            if (!window.confirm(WARNING_MM)) {
                event.preventDefault();
            } else {
                isSubmitting = true; // user accepted the loss; stop double-prompting
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
