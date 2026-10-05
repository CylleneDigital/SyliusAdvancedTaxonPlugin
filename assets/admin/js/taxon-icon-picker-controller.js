import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'typeSelect',
        'iconInput',
        'fileInput',
        'pickerButton',
        'iconRow',
        'fileRow',
        'modal',
        'iconButton',
    ];

    connect() {
        this.bootstrapModal = this.hasModalTarget && window.bootstrap && window.bootstrap.Modal
            ? window.bootstrap.Modal.getOrCreateInstance(this.modalTarget)
            : null;
        this.fallbackBackdrop = null;

        this.updateIconMode();
    }

    disconnect() {
        this.removeFallbackBackdrop();
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

        if (this.hasPickerButtonTarget) {
            this.pickerButtonTarget.style.display = isIconMode ? 'inline-flex' : 'none';
        }
    }

    openModal(event) {
        if (event) {
            event.preventDefault();
        }

        this.highlightSelectedIcon();

        if (!this.hasModalTarget) {
            return;
        }

        if (this.bootstrapModal) {
            this.bootstrapModal.show();

            return;
        }

        this.modalTarget.style.display = 'block';
        this.modalTarget.classList.add('show');
        this.modalTarget.removeAttribute('aria-hidden');
        this.modalTarget.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');

        this.fallbackBackdrop = document.createElement('div');
        this.fallbackBackdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(this.fallbackBackdrop);
    }

    closeModal(event) {
        if (event) {
            event.preventDefault();
        }

        if (!this.hasModalTarget) {
            return;
        }

        if (this.bootstrapModal) {
            this.bootstrapModal.hide();

            return;
        }

        this.modalTarget.classList.remove('show');
        this.modalTarget.style.display = 'none';
        this.modalTarget.setAttribute('aria-hidden', 'true');
        this.modalTarget.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');

        this.removeFallbackBackdrop();

        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
            backdrop.remove();
        });
    }

    closeOnBackdrop(event) {
        if (!this.hasModalTarget) {
            return;
        }

        if (event.target === this.modalTarget) {
            this.closeModal();
        }
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

    removeFallbackBackdrop() {
        if (this.fallbackBackdrop) {
            this.fallbackBackdrop.remove();
            this.fallbackBackdrop = null;
        }
    }
}
