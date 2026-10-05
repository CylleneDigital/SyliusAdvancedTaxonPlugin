import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        mode: { type: String, default: 'panel' },
    };

    connect() {
        if (this.modeValue === 'trigger') {
            this.setToggleExpanded(false);

            return;
        }

        this.desktopOpen = true;
        this.mobileMediaQuery = window.matchMedia('(max-width: 991.98px)');
        this._handleViewportChange = this.handleViewportChange.bind(this);
        this._handleKeydown = this.handleKeydown.bind(this);
        this._handleExternalToggle = this.handleExternalToggle.bind(this);

        this.mobileMediaQuery.addEventListener('change', this._handleViewportChange);

        document.addEventListener('keydown', this._handleKeydown);
        window.addEventListener('at-advanced-filters:toggle', this._handleExternalToggle);
        this.syncStateForViewport();
    }

    disconnect() {
        if (this.modeValue === 'trigger') {
            return;
        }

        if (this.mobileMediaQuery) {
            this.mobileMediaQuery.removeEventListener('change', this._handleViewportChange);
        }

        document.removeEventListener('keydown', this._handleKeydown);
        window.removeEventListener('at-advanced-filters:toggle', this._handleExternalToggle);
        document.body.classList.remove('at-advanced-filters-mobile-open');
    }

    toggle(event) {
        if (event) {
            event.preventDefault();
        }

        if (this.modeValue === 'trigger') {
            window.dispatchEvent(new CustomEvent('at-advanced-filters:toggle'));

            return;
        }

        if (this.isMobileViewport()) {
            this.toggleMobile();

            return;
        }

        this.toggleDesktop();
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }

        if (this.isMobileViewport()) {
            this.setMobileOpen(false);
        }
    }

    handleViewportChange() {
        this.syncStateForViewport();
    }

    handleExternalToggle() {
        if (this.isMobileViewport()) {
            this.toggleMobile();

            return;
        }

        this.toggleDesktop();
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && this.isMobileViewport()) {
            this.setMobileOpen(false);
        }
    }

    toggleDesktop() {
        const panel = this.getPanelElement();
        if (!panel) {
            return;
        }

        this.desktopOpen = !this.desktopOpen;
        panel.classList.toggle('at-advanced-filters--desktop-hidden', !this.desktopOpen);
        this.updateDesktopLayout();
        this.setToggleExpanded(this.desktopOpen);
    }

    toggleMobile() {
        const panel = this.getPanelElement();
        if (!panel) {
            return;
        }

        const isOpen = panel.classList.contains('at-advanced-filters--mobile-open');
        this.setMobileOpen(!isOpen);
    }

    setMobileOpen(open) {
        const panel = this.getPanelElement();
        if (!panel) {
            return;
        }

        panel.classList.toggle('at-advanced-filters--mobile-open', open);

        const backdrop = this.getBackdropElement();
        if (backdrop) {
            backdrop.classList.toggle('at-advanced-filters-backdrop--visible', open);
        }

        document.body.classList.toggle('at-advanced-filters-mobile-open', open);
        this.setToggleExpanded(open);

        if (open) {
            panel.querySelector('button, input, a[href]')?.focus({ preventScroll: true });
        }
    }

    syncStateForViewport() {
        const panel = this.getPanelElement();
        if (!panel) {
            return;
        }

        if (this.isMobileViewport()) {
            panel.classList.remove('at-advanced-filters--desktop-hidden');
            this.setMobileOpen(false);

            return;
        }

        panel.classList.remove('at-advanced-filters--mobile-open');
        const backdrop = this.getBackdropElement();
        if (backdrop) {
            backdrop.classList.remove('at-advanced-filters-backdrop--visible');
        }

        document.body.classList.remove('at-advanced-filters-mobile-open');
        panel.classList.toggle('at-advanced-filters--desktop-hidden', !this.desktopOpen);
        this.updateDesktopLayout();
        this.setToggleExpanded(this.desktopOpen);
    }

    updateDesktopLayout() {
        const sidebarColumn = document.getElementById('at-advanced-filters-sidebar-column');
        const mainColumn = document.getElementById('at-advanced-filters-main-column');

        if (!sidebarColumn || !mainColumn) {
            return;
        }

        const shouldCollapse = !this.isMobileViewport() && !this.desktopOpen;

        sidebarColumn.classList.toggle('d-lg-none', shouldCollapse);
        mainColumn.classList.toggle('col-lg-12', shouldCollapse);
        mainColumn.classList.toggle('col-lg-9', !shouldCollapse);
    }

    isMobileViewport() {
        return this.mobileMediaQuery.matches;
    }

    getPanelElement() {
        return document.getElementById('at-advanced-filters-panel');
    }

    getBackdropElement() {
        return document.querySelector('.at-advanced-filters-backdrop');
    }

    setToggleExpanded(expanded) {
        document.querySelectorAll('[data-at-advanced-filters-role="toggle"]').forEach((toggleButton) => {
            toggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }
}