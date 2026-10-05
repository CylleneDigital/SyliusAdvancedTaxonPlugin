import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track', 'slide'];
    static values = {
        interval: { type: Number, default: 5000 },
    };

    connect() {
        this.index = 0;
        this.timer = null;

        this.show(this.index);
        this.startAuto();

        this._pause = () => this.stopAuto();
        this._resume = () => this.startAuto();

        this.element.addEventListener('mouseenter', this._pause);
        this.element.addEventListener('mouseleave', this._resume);
    }

    disconnect() {
        this.stopAuto();
        this.element.removeEventListener('mouseenter', this._pause);
        this.element.removeEventListener('mouseleave', this._resume);
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
        });
    }

    startAuto() {
        if (this.slideTargets.length < 2 || this.timer !== null) {
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
