/* Progressive UI helpers. Forms, authentication and AI remain server-owned. */
(() => {
    'use strict';
    if (window.pbrExperienceReady) return;
    window.pbrExperienceReady = true;
    const eye = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';

    function enhance() {
        document.querySelectorAll('.auth-card input[type="password"]').forEach(input => {
            if (input.dataset.pbrPassword) return;
            input.dataset.pbrPassword = 'ready';
            const wrapper = document.createElement('div');
            wrapper.className = 'pbr-password-wrap';
            input.before(wrapper);
            wrapper.append(input);
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'pbr-password-toggle';
            toggle.innerHTML = eye;
            toggle.setAttribute('aria-label', 'Show password');
            toggle.setAttribute('aria-pressed', 'false');
            if (input.id) toggle.setAttribute('aria-controls', input.id);
            toggle.addEventListener('click', () => {
                const reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
                toggle.setAttribute('aria-pressed', String(reveal));
            });
            wrapper.append(toggle);
        });
    }

    function resizeComposer() {
        const input = document.getElementById('portal-message');
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = Math.min(150, Math.max(47, input.scrollHeight)) + 'px';
        input.style.overflowY = input.scrollHeight > 150 ? 'auto' : 'hidden';
    }

    document.addEventListener('input', event => {
        if (event.target.id === 'portal-message') resizeComposer();
    });
    document.addEventListener('click', event => {
        if (event.target.closest('[data-ai-prompt]')) queueMicrotask(resizeComposer);
    });
    // Leave asynchronously handled forms (including streamed AI) untouched.
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('[data-pbr-loading]')) return;
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            form.setAttribute('aria-busy', 'true');
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-pbr-loading][aria-busy]').forEach(form => form.removeAttribute('aria-busy'));
        resizeComposer();
    });
    document.addEventListener('livewire:navigated', enhance);
    enhance();
    resizeComposer();
})();
