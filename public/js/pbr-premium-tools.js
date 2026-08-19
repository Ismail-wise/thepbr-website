(() => {
    const toolPages = document.querySelectorAll('[data-pbr-premium-tool]');

    if (!toolPages.length) {
        return;
    }

    const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    const editableFormSelectors = [
        '#pbrOperatingToolForm',
        '#chapter-one-tool-form',
        '#startup-capital-builder',
    ];

    const setSaveState = (toolbar, label, state = '') => {
        const saveState = toolbar.querySelector('[data-pbr-save-state]');

        if (!saveState) {
            return;
        }

        saveState.textContent = label;
        saveState.classList.toggle('is-dirty', state === 'dirty');
        saveState.classList.toggle('is-processing', state === 'processing');
    };

    const setupDirtyState = (page, toolbar) => {
        const form = editableFormSelectors
            .map((selector) => page.querySelector(selector))
            .find(Boolean);

        if (!form) {
            return;
        }

        let dirty = false;

        const markDirty = (event) => {
            const target = event.target;

            if (
                !(target instanceof HTMLInputElement)
                && !(target instanceof HTMLSelectElement)
                && !(target instanceof HTMLTextAreaElement)
            ) {
                return;
            }

            if (
                target instanceof HTMLInputElement
                && ['hidden', 'submit', 'button'].includes(target.type)
            ) {
                return;
            }

            dirty = true;
            page.dataset.pbrDirty = 'true';
            setSaveState(toolbar, 'Unsaved Input', 'dirty');
        };

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        form.addEventListener('submit', () => {
            if (dirty) {
                setSaveState(toolbar, 'Processing…', 'processing');
            }
        });
    };

    const setupSectionNavigation = (page, toolbar) => {
        const links = Array.from(
            toolbar.querySelectorAll('[data-pbr-section-link]')
        );

        const targets = links
            .map((link) => {
                const targetId = link.dataset.pbrSectionLink;
                const target = targetId
                    ? page.querySelector(`#${CSS.escape(targetId)}`)
                    : null;

                if (!target) {
                    link.classList.add('is-disabled');
                    link.setAttribute('aria-disabled', 'true');

                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                    });

                    return null;
                }

                link.addEventListener('click', (event) => {
                    event.preventDefault();

                    target.scrollIntoView({
                        behavior: prefersReducedMotion ? 'auto' : 'smooth',
                        block: 'start',
                    });
                });

                return { link, target };
            })
            .filter(Boolean);

        if (!targets.length || !('IntersectionObserver' in window)) {
            return;
        }

        const activate = (activeLink) => {
            links.forEach((link) => {
                link.classList.toggle('is-active', link === activeLink);
            });
        };

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

                if (!visible) {
                    return;
                }

                const match = targets.find(
                    ({ target }) => target === visible.target
                );

                if (match) {
                    activate(match.link);
                }
            },
            {
                rootMargin: '-165px 0px -58% 0px',
                threshold: [0.05, 0.25, 0.6],
            }
        );

        targets.forEach(({ target }) => observer.observe(target));
    };

    const setupToolbarElevation = (toolbar) => {
        const update = () => {
            toolbar.classList.toggle('is-scrolled', window.scrollY > 104);
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
    };

    toolPages.forEach((page) => {
        const toolbar = page.querySelector('[data-pbr-premium-toolbar]');

        if (!toolbar) {
            return;
        }

        setupDirtyState(page, toolbar);
        setupSectionNavigation(page, toolbar);
        setupToolbarElevation(toolbar);
    });
})();
