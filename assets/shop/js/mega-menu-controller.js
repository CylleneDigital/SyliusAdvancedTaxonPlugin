import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    showImage(event) {
        const media = this.element.querySelector('.at-mn-media');
        if (!media) {
            return;
        }

        const imageUrl = event.currentTarget?.dataset?.megaMenuImage ?? '';
        if (!imageUrl) {
            media.removeAttribute('src');
            media.setAttribute('hidden', 'hidden');
            return;
        }

        media.setAttribute('src', imageUrl);
        media.removeAttribute('hidden');
    }

    clearImage() {
        const media = this.element.querySelector('.at-mn-media');
        if (!media) {
            return;
        }

        media.removeAttribute('src');
        media.setAttribute('hidden', 'hidden');
    }
}
