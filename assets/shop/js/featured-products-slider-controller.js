import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track', 'prev', 'next'];

    connect() {
        if (this.hasTrackTarget) {
            this.trackTarget.addEventListener('scroll', this.updateButtons, { passive: true });
        }

        window.addEventListener('resize', this.updateButtons);
        this.updateButtons();
    }

    disconnect() {
        if (this.hasTrackTarget) {
            this.trackTarget.removeEventListener('scroll', this.updateButtons);
        }

        window.removeEventListener('resize', this.updateButtons);
    }

    previous() {
        if (!this.hasTrackTarget) {
            return;
        }

        this.trackTarget.scrollBy({ left: -this.itemStep(), behavior: 'smooth' });
    }

    next() {
        if (!this.hasTrackTarget) {
            return;
        }

        this.trackTarget.scrollBy({ left: this.itemStep(), behavior: 'smooth' });
    }

    updateButtons = () => {
        if (!this.hasTrackTarget || !this.hasPrevTarget || !this.hasNextTarget) {
            return;
        }

        const maxScrollLeft = Math.max(this.trackTarget.scrollWidth - this.trackTarget.clientWidth, 0);
        this.prevTarget.disabled = this.trackTarget.scrollLeft <= 2;
        this.nextTarget.disabled = this.trackTarget.scrollLeft >= (maxScrollLeft - 2);
    };

    itemStep() {
        if (!this.hasTrackTarget) {
            return 320;
        }

        const firstItem = this.trackTarget.firstElementChild;
        if (!firstItem) {
            return 320;
        }

        const itemWidth = firstItem.getBoundingClientRect().width;
        const trackStyle = window.getComputedStyle(this.trackTarget);
        const gap = parseFloat(trackStyle.columnGap || trackStyle.gap || '0');

        return itemWidth + gap;
    }
}
