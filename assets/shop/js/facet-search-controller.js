import { Controller } from '@hotwired/stimulus';

/**
 * Filters the values of a single facet list, so long lists stay usable.
 *
 * The checkboxes are only hidden, never removed: a value checked before typing a search stays
 * submitted with the filter form, even when the search hides it.
 */
export default class extends Controller {
    static targets = ['input', 'item', 'label', 'empty'];

    connect() {
        this.syncEmptyState();
    }

    filter() {
        const query = this.normalize(this.inputTarget.value);
        let visibleCount = 0;

        this.itemTargets.forEach((item, index) => {
            const label = this.labelTargets[index] ?? item;
            const matches = query === '' || this.normalize(label.textContent).includes(query);

            item.classList.toggle('d-none', !matches);

            if (matches) {
                visibleCount += 1;
            }
        });

        this.syncEmptyState(visibleCount);
    }

    syncEmptyState(visibleCount = this.itemTargets.length) {
        if (!this.hasEmptyTarget) {
            return;
        }

        this.emptyTarget.classList.toggle('d-none', visibleCount > 0);
    }

    /**
     * Lowercases and strips diacritics so that "ete" matches "Été".
     */
    normalize(value) {
        return (value ?? '')
            .toString()
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }
}
