/*
 * PBR Startup Capital Premium Reference
 * Progressive disclosure and layout orchestration only.
 * No calculation, persistence, approval or routing behavior is changed here.
 */

(() => {
    'use strict';

    const onReady = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    };

    const buildDetails = ({
        className,
        title,
        description,
        content,
        open = false,
    }) => {
        const details = document.createElement('details');
        details.className = `pbr-reference-details ${className || ''}`.trim();
        details.open = open;

        const summary = document.createElement('summary');
        summary.innerHTML = `
            <div>
                <strong>${title}</strong>
                <small>${description}</small>
            </div>
            <span aria-hidden="true">＋</span>
        `;

        details.appendChild(summary);
        details.appendChild(content);

        return details;
    };

    onReady(() => {
        const page = document.querySelector('.pbr-capital-plan-page');

        if (!page || page.dataset.referenceExperience === 'ready') {
            return;
        }

        page.dataset.referenceExperience = 'ready';
        page.classList.add('pbr-startup-reference-v1');

        const form = page.querySelector('#startup-capital-builder');
        const workspace = page.querySelector('#startup-capital-workspace');
        const operatingContext = page.querySelector('.pbr-operating-context');
        const formHasErrors = Boolean(page.querySelector('.pbr-form-errors'));

        if (form && workspace && operatingContext && !operatingContext.closest('.pbr-reference-governance')) {
            const governance = buildDetails({
                className: 'pbr-reference-governance',
                title: 'Plan Settings & Governance',
                description: 'Owner, effective date, decision summary, evidence and operating actions. Open this when the plan is ready for management review.',
                content: operatingContext,
                open: formHasErrors,
            });

            workspace.insertAdjacentElement('afterend', governance);
        }

        const helpCard = page.querySelector('.pbr-capital-help-card');

        if (helpCard && !helpCard.closest('.pbr-reference-help')) {
            const help = buildDetails({
                className: 'pbr-reference-help',
                title: 'How to use this planner',
                description: 'A short four-step guide for first-time setup.',
                content: helpCard,
                open: false,
            });

            helpCard.parentNode.insertBefore(help, helpCard);
        }

        const savedPlansRoot = page.querySelector('#saved-plans');
        const scenarioManager = savedPlansRoot?.querySelector('.pbr-scenario-manager');

        if (savedPlansRoot && scenarioManager && !scenarioManager.closest('.pbr-reference-saved-plans')) {
            const draftCount = page.querySelector('.pbr-scenario-count')?.textContent?.trim();
            const savedPlans = buildDetails({
                className: 'pbr-reference-saved-plans',
                title: 'Working Plans & Rule History',
                description: draftCount
                    ? `${draftCount}. Open to compare, rename, duplicate, review or activate a plan.`
                    : 'Open to manage working plans and rule versions.',
                content: scenarioManager,
                open: false,
            });

            savedPlansRoot.appendChild(savedPlans);
        }

        const actionBoard = page.querySelector('.pbr-operating-action-board');

        if (actionBoard && !actionBoard.closest('.pbr-reference-actions')) {
            const actions = buildDetails({
                className: 'pbr-reference-actions',
                title: 'Operating Actions',
                description: 'Tasks created from this capital decision stay available here without competing with the planning workspace.',
                content: actionBoard,
                open: false,
            });

            actionBoard.parentNode.insertBefore(actions, actionBoard);
        }

        const resultCard = page.querySelector('.pbr-capital-summary-card');
        const resultNav = page.querySelector('[href="#result"]');

        if (resultCard && resultNav) {
            resultNav.addEventListener('click', () => {
                window.setTimeout(() => {
                    resultCard.focus?.({ preventScroll: true });
                }, 350);
            });
        }
    });
})();
