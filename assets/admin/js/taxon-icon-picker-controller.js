import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'typeSelect',
        'iconInput',
        'iconRow',
        'fileRow',
        'modal',
        'iconButton',
    ];

    connect() {
        // The Sylius admin entrypoint exposes Bootstrap as window.bootstrap.
        this.modal = this.hasModalTarget ? window.bootstrap.Modal.getOrCreateInstance(this.modalTarget) : null;

        this.updateIconMode();
    }

    updateIconMode() {
        if (!this.hasTypeSelectTarget || !this.hasIconInputTarget) {
            return;
        }

        const isIconMode = (this.typeSelectTarget.value || 'icon') === 'icon';

        if (this.hasIconRowTarget) {
            this.iconRowTarget.style.display = isIconMode ? 'block' : 'none';
        }

        if (this.hasFileRowTarget) {
            this.fileRowTarget.style.display = isIconMode ? 'none' : 'block';
        }
    }

    openModal(event) {
        if (event) {
            event.preventDefault();
        }

        this.highlightSelectedIcon();
        this.modal?.show();
    }

    closeModal(event) {
        if (event) {
            event.preventDefault();
        }

        this.modal?.hide();
    }

    selectIcon(event) {
        const button = event.currentTarget;
        if (!button || !this.hasIconInputTarget) {
            return;
        }

        this.iconInputTarget.value = button.getAttribute('data-icon-value') || '';
        this.highlightSelectedIcon();
        this.closeModal();
    }

    highlightSelectedIcon() {
        if (!this.hasIconInputTarget) {
            return;
        }

        const currentValue = this.normalizeIconValue(this.iconInputTarget.value);
        const fallbackTablerValue = currentValue.includes(':') ? currentValue : `tabler:${currentValue}`;

        this.iconButtonTargets.forEach((button) => {
            const buttonValue = this.normalizeIconValue(button.getAttribute('data-icon-value'));
            const isSelected = currentValue !== '' && (buttonValue === currentValue || buttonValue === fallbackTablerValue);

            button.classList.toggle('btn-primary', isSelected);
            button.classList.toggle('btn-outline-secondary', !isSelected);
            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
    }

    normalizeIconValue(value) {
        return (value || '').toLowerCase().trim();
    }
}
