/*
 * The left column of the taxon form (tabs and taxon tree) is sticky, which makes it a stacking
 * context: a modal opened from it, such as the delete confirmation of the taxon tree, would stay
 * under the page backdrop. It is moved under <body> before it shows; Sylius puts it back when the
 * modal is hidden.
 */
document.addEventListener('show.bs.modal', (event) => {
    const modal = event.target;

    if (modal instanceof HTMLElement && modal.closest('.col-12:has(> #side-nav)')) {
        document.body.appendChild(modal);
    }
});
