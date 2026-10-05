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
        this._syncScreens();
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
        this._focusActiveScreen();
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
        this._syncScreens();
        this._focusActiveScreen();
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
        this._syncScreens();
        this._focusActiveScreen();
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
        if (!this.element.classList.contains('at-mn-drawer--open')) { return; }

        if (event.key === 'Escape') {
            this.close();

            return;
        }

        // The drawer is a modal dialog: Tab cycles through the active screen.
        if (event.key === 'Tab') {
            const focusables = this._focusables();
            if (focusables.length === 0) { return; }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            } else if (!this.element.contains(document.activeElement)) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    // Screens slid aside stay in the DOM: inert keeps their links out of the keyboard and screen readers.
    _syncScreens() {
        this.element.querySelectorAll('.at-mn-screen').forEach((screen) => {
            screen.inert = screen.dataset.mnState !== 'active';
        });
    }

    _focusables() {
        return Array.from(this.element.querySelectorAll('a[href], button:not([disabled])'))
            .filter((element) => !element.closest('[inert]'));
    }

    _focusActiveScreen() {
        const [first] = this._focusables();
        if (first) {
            first.focus({ preventScroll: true });
        }
    }

    _resetScreens() {
        this.element.querySelectorAll('.at-mn-screen').forEach((screen) => {
            screen.dataset.mnState = screen.id === 'at-mn-screen-root' ? 'active' : 'right';
        });
        this._stack = ['at-mn-screen-root'];
        this._syncScreens();
    }
}
