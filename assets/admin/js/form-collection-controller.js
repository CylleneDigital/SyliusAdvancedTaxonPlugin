import { Controller } from '@hotwired/stimulus';
import { nextCollectionIndex } from './collection-index.js';

export default class extends Controller {
    add(event) {
        event.preventDefault();

        const collection = event.currentTarget.closest('[data-form-type="collection"]');
        if (!collection) {
            return;
        }

        const list = collection.querySelector('[data-form-collection="list"]');
        const prototype = collection.getAttribute('data-prototype');
        if (!list || !prototype) {
            return;
        }

        const prototypeName = collection.getAttribute('data-prototype-name') || '__name__';
        const nextIndex = nextCollectionIndex(collection, list, prototype, prototypeName);
        const html = prototype
            .replaceAll(prototypeName, String(nextIndex))
            .replaceAll('__name__', String(nextIndex));

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const item = wrapper.firstElementChild;
        if (item) {
            list.appendChild(item);
            this.prefillPosition(list, item);
        }
    }

    remove(event) {
        event.preventDefault();

        const item = event.currentTarget.closest('[data-form-collection="item"]');
        if (item) {
            item.remove();
        }
    }

    /**
     * Pre-fills the position of a freshly added item with the highest position used by the other
     * items of the collection, so it lands after the ones already entered. The prototype already
     * carries the value computed from the persisted items; this keeps it accurate when several
     * items are added without reloading the page.
     */
    prefillPosition(list, item) {
        const positionInput = item.querySelector('input[name$="[position]"]');
        if (!positionInput) {
            return;
        }

        let highestPosition = null;
        list.querySelectorAll('input[name$="[position]"]').forEach((input) => {
            if (input === positionInput) {
                return;
            }

            const position = Number.parseInt(input.value, 10);
            if (!Number.isNaN(position) && (highestPosition === null || position > highestPosition)) {
                highestPosition = position;
            }
        });

        if (highestPosition === null) {
            return;
        }

        positionInput.value = String(highestPosition + 1);
    }
}
