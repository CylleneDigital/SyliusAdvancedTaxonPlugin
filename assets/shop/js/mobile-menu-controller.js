import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for the mobile mega menu drill-down drawer.
 *
 * Usage: add data-controller="at-mobile-menu" on the drawer element.
 * The burger button (#at-mn-burger) can be anywhere in the DOM; the controller
 * finds it by ID and binds to it using a capture-phase listener so Bootstrap
 * Offcanvas event handlers cannot intercept the click first.
 *
 * Data attributes on inner elements:
 *   data-action="at-mobile-menu#goTo"    data-at-mobile-menu-goto-param="[screen-id]"
 *   data-action="at-mobile-menu#goBack"
 *   data-action="at-mobile-menu#close"
 */
export default class extends Controller {

    initialize() {
        this._stack = ['at-mn-screen-root'];
        this._openHandler  = this._handleBurgerClick.bind(this);
        this._keyHandler   = this._handleKey.bind(this);
    }

    connect() {
        const burger = document.getElementById('at-mn-burger');
        if (burger) {
            // Capture phase: fires before any bubbling Bootstrap handlers.
            burger.addEventListener('click', this._openHandler, true);
        }
        document.addEventListener('keydown', this._keyHandler);
    }

    disconnect() {
        const burger = document.getElementById('at-mn-burger');
        if (burger) {
            burger.removeEventListener('click', this._openHandler, true);
        }
        document.removeEventListener('keydown', this._keyHandler);
    }

    // ------------------------------------------------------------------ //
    // Public actions (callable via data-action)
    // ------------------------------------------------------------------ //

    open() {
        this.element.classList.add('at-mn-drawer--open');
        this.element.setAttribute('aria-hidden', 'false');
        document.body.classList.add('at-mn-no-scroll');
        const burger = document.getElementById('at-mn-burger');
        if (burger) {
            burger.setAttribute('aria-expanded', 'true');
        }
    }

    close() {
        this.element.classList.remove('at-mn-drawer--open');
        this.element.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('at-mn-no-scroll');
        const burger = document.getElementById('at-mn-burger');
        if (burger) {
            burger.setAttribute('aria-expanded', 'false');
            burger.focus();
        }
        // Reset screens after the close transition completes.
        setTimeout(() => {
            if (!this.element.classList.contains('at-mn-drawer--open')) {
                this._resetScreens();
            }
        }, 300);
    }

    /**
     * Navigate forward to a target screen.
     * Expects a Stimulus param: data-at-mobile-menu-goto-param="[screen-id]"
     */
    goTo(event) {
        const targetId  = event.params.goto;
        const currentId = this._stack[this._stack.length - 1];
        if (!targetId || currentId === targetId) { return; }

        const currentEl = document.getElementById(currentId);
        const targetEl  = document.getElementById(targetId);
        if (!targetEl) { return; }

        if (currentEl) { currentEl.dataset.mnState = 'left'; }

        // Place target off-screen right, then transition to active.
        targetEl.dataset.mnState = 'right';
        void targetEl.offsetWidth; // force reflow
        targetEl.dataset.mnState  = 'active';
        targetEl.scrollTop = 0;

        this._stack.push(targetId);
    }

    /** Navigate back to the previous screen. */
    goBack() {
        if (this._stack.length <= 1) { this.close(); return; }
        const currentId = this._stack.pop();
        const prevId    = this._stack[this._stack.length - 1];
        const currentEl = document.getElementById(currentId);
        const prevEl    = document.getElementById(prevId);
        if (currentEl) { currentEl.dataset.mnState = 'right'; }
        if (prevEl)    { prevEl.dataset.mnState    = 'active'; }
    }

    // ------------------------------------------------------------------ //
    // Private helpers
    // ------------------------------------------------------------------ //

    _handleBurgerClick(event) {
        // Prevent Bootstrap (or any other handler) from processing this click.
        event.stopImmediatePropagation();
        this.open();
    }

    _handleKey(event) {
        if (event.key === 'Escape' && this.element.classList.contains('at-mn-drawer--open')) {
            this.close();
        }
    }

    _resetScreens() {
        this.element.querySelectorAll('.at-mn-screen').forEach((screen) => {
            screen.dataset.mnState = screen.id === 'at-mn-screen-root' ? 'active' : 'right';
        });
        this._stack = ['at-mn-screen-root'];
    }
}
