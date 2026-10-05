import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['slide'];
    static values = {
        interval: { type: Number, default: 5000 },
    };

    connect() {
        this.index = 0;
        this.timer = null;

        this.show(this.index);
        this.startAuto();

        // Paused while hovered or while it holds the focus.
        this._pause = () => this.stopAuto();
        this._resume = (event) => {
            if (event.type === 'focusout' && this.element.contains(event.relatedTarget)) {
                return;
            }
            this.startAuto();
        };

        this.element.addEventListener('mouseenter', this._pause);
        this.element.addEventListener('mouseleave', this._resume);
        this.element.addEventListener('focusin', this._pause);
        this.element.addEventListener('focusout', this._resume);
    }

    disconnect() {
        this.stopAuto();
        this.element.removeEventListener('mouseenter', this._pause);
        this.element.removeEventListener('mouseleave', this._resume);
        this.element.removeEventListener('focusin', this._pause);
        this.element.removeEventListener('focusout', this._resume);
    }

    previous() {
        if (this.slideTargets.length < 2) {
            return;
        }

        this.index = (this.index - 1 + this.slideTargets.length) % this.slideTargets.length;
        this.show(this.index);
    }

    next() {
        if (this.slideTargets.length < 2) {
            return;
        }

        this.index = (this.index + 1) % this.slideTargets.length;
        this.show(this.index);
    }

    show(index) {
        this.slideTargets.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === index);
            // A hidden slide keeps its link out of the keyboard order.
            slide.inert = slideIndex !== index;
        });
    }

    startAuto() {
        if (this.slideTargets.length < 2 || this.timer !== null || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        this.timer = window.setInterval(() => {
            this.next();
        }, this.intervalValue);
    }

    stopAuto() {
        if (this.timer !== null) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }
}
