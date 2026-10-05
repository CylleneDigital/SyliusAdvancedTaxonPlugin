import { Controller } from '@hotwired/stimulus';

const ERROR_SELECTOR = '.is-invalid, .invalid-feedback, [data-test-facet-condition-errors]';

/*
 * The taxon form spreads its fields over tabs and folded zones: after a refused submission, the
 * tabs holding an error are flagged, the first of them is shown and the zones holding an error
 * are unfolded, so that the merchant sees what to fix.
 */
export default class extends Controller {
    connect() {
        let firstTab = null;

        this.element.querySelectorAll('.tab-pane').forEach((pane) => {
            if (!pane.id || !pane.querySelector(ERROR_SELECTOR)) {
                return;
            }

            const tab = document.querySelector(`[data-bs-target="#${pane.id}"]`);
            if (tab) {
                tab.classList.add('at-admin-tab-invalid');
                firstTab ??= tab;
            }

            pane.querySelectorAll('.accordion-collapse').forEach((collapse) => {
                if (collapse.querySelector(ERROR_SELECTOR)) {
                    this.unfold(collapse);
                }
            });
        });

        if (firstTab && !firstTab.classList.contains('active') && window.bootstrap?.Tab) {
            window.bootstrap.Tab.getOrCreateInstance(firstTab).show();
        }
    }

    unfold(collapse) {
        collapse.classList.add('show');
        document.querySelectorAll(`[data-bs-target="#${collapse.id}"]`).forEach((button) => {
            button.classList.remove('collapsed');
            button.setAttribute('aria-expanded', 'true');
        });
    }
}
