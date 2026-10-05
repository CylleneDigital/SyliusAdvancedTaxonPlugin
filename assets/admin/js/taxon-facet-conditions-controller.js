import { Controller } from '@hotwired/stimulus';
import { nextCollectionIndex } from './collection-index.js';

export default class extends Controller {
    static targets = ['list', 'items', 'previewSummary', 'previewList', 'previewError', 'previewLoading'];
    static values = {
        previewUrl: String,
        csrfToken: String,
        messages: Object,
        choices: Object,
        // FacetCondition::OPERATORS_BY_TYPE, TYPES_WITH_REFERENCE and TYPES_WITHOUT_VALUE.
        rules: Object,
    };

    message(key, count = null) {
        const text = this.messagesValue[key] || key;

        return count === null ? text : text.replace('%count%', String(count));
    }

    connect() {
        this.operatorsByType = this.rulesValue.operatorsByType;
        this.typesWithReference = this.rulesValue.typesWithReference;
        this.typesWithoutValue = this.rulesValue.typesWithoutValue;

        this.initializeExistingRows();
        this.syncConditionalIndicator();
    }

    handleChange(event) {
        const target = event.target;
        if (!target) {
            return;
        }

        if (target.matches('[data-facet-type]')) {
            const row = target.closest('[data-facet-condition-row]');
            if (row) {
                this.updateRowForType(row, target.value);
            }
            return;
        }

        if (target.matches('[data-facet-reference-select]')) {
            const row = target.closest('[data-facet-condition-row]');
            if (!row) {
                return;
            }

            const hidden = row.querySelector('[data-facet-reference-code]');
            if (hidden) {
                hidden.value = target.value;
            }
        }
    }

    addCondition(event) {
        if (event) {
            event.preventDefault();
        }

        if (!this.hasListTarget || !this.hasItemsTarget) {
            return;
        }

        const prototype = this.listTarget.dataset.prototype;
        if (!prototype) {
            return;
        }

        const prototypeName = this.listTarget.dataset.prototypeName || '__facet__';
        const nextIndex = nextCollectionIndex(this.listTarget, this.itemsTarget, prototype, prototypeName);
        const escapedPrototypeName = prototypeName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const html = prototype.replace(new RegExp(escapedPrototypeName, 'g'), String(nextIndex));

        const tmp = document.createElement('div');
        tmp.innerHTML = html.trim();

        const newRow = tmp.firstElementChild;
        if (!newRow) {
            return;
        }

        this.itemsTarget.appendChild(newRow);
        this.initConditionRow(newRow);
        this.syncPositions();
        this.syncConditionalIndicator();
        newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    removeCondition(event) {
        if (event) {
            event.preventDefault();
        }

        const button = event?.currentTarget;
        const row = button?.closest('[data-facet-condition-row]');
        if (!row) {
            return;
        }

        row.remove();
        this.syncPositions();
        this.syncConditionalIndicator();
    }

    initializeExistingRows() {
        this.element.querySelectorAll('[data-facet-condition-row]').forEach((row) => {
            this.initConditionRow(row);
        });
    }

    initConditionRow(row) {
        const typeSelect = row.querySelector('[data-facet-type]');
        if (!typeSelect) {
            return;
        }

        this.updateRowForType(row, typeSelect.value);
    }

    syncPositions() {
        if (!this.hasItemsTarget) {
            return;
        }

        const rows = this.itemsTarget.querySelectorAll('[data-facet-condition-row]');
        rows.forEach((row, index) => {
            const positionInput = row.querySelector('input[name$="[position]"]');
            if (positionInput) {
                positionInput.value = String(index);
            }
        });
    }

    updateRowForType(row, type) {
        const operatorSelect = row.querySelector('[data-facet-operator]');
        const referenceContainer = row.querySelector('[data-facet-reference-container]');
        const referenceSelect = row.querySelector('[data-facet-reference-select]');
        const referenceCodeInput = row.querySelector('[data-facet-reference-code]');
        const valueContainer = row.querySelector('[data-facet-value-container]');

        if (operatorSelect) {
            const allowed = this.operatorsByType[type] || [];
            Array.from(operatorSelect.options).forEach((option) => {
                option.hidden = !allowed.includes(option.value);
            });

            if (!allowed.includes(operatorSelect.value)) {
                operatorSelect.value = allowed[0] || '';
            }
        }

        const needsReference = this.typesWithReference.includes(type);
        if (referenceContainer) {
            referenceContainer.style.display = needsReference ? '' : 'none';
        }

        if (referenceSelect && needsReference) {
            const choices = this.choicesValue[type] || {};
            const currentCode = referenceCodeInput ? referenceCodeInput.value : '';

            referenceSelect.innerHTML = '<option value="">-</option>';
            Object.keys(choices).sort().forEach((label) => {
                const option = document.createElement('option');
                option.value = choices[label];
                option.textContent = label;
                if (option.value === currentCode) {
                    option.selected = true;
                }

                referenceSelect.appendChild(option);
            });
        }

        if (valueContainer) {
            valueContainer.style.display = this.typesWithoutValue.includes(type) ? 'none' : '';
        }
    }

    syncConditionalIndicator() {
        const indicator = this.element.querySelector('[data-facet-conditional-indicator]');
        if (!indicator) {
            return;
        }

        const rowCount = this.hasItemsTarget
            ? this.itemsTarget.querySelectorAll('[data-facet-condition-row]').length
            : 0;

        indicator.checked = rowCount > 0;
    }

    async previewConditions(event) {
        if (event) {
            event.preventDefault();
        }

        if (!this.previewUrlValue) {
            return;
        }

        this.setPreviewLoading(true);
        this.clearPreviewError();
        this.renderPreviewSummary('');
        this.clearPreviewList();

        const conditions = this.collectConditionsPayload();

        try {
            const response = await fetch(this.previewUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfTokenValue,
                },
                body: JSON.stringify({ conditions }),
            });

            if (!response.ok) {
                throw new Error('preview_failed');
            }

            const data = await response.json();
            const count = Number(data.count || 0);
            const products = Array.isArray(data.products) ? data.products : [];
            const limit = Number(data.limit || products.length || 0);

            this.renderPreviewSummary(this.message('matching', count));
            this.renderPreviewProducts(products, count, limit);
        } catch (error) {
            this.renderPreviewSummary(this.message('unavailable'));
            this.renderPreviewError(this.message('error'));
        } finally {
            this.setPreviewLoading(false);
        }
    }

    collectConditionsPayload() {
        if (!this.hasItemsTarget) {
            return [];
        }

        const rows = this.itemsTarget.querySelectorAll('[data-facet-condition-row]');
        const payload = [];

        rows.forEach((row, index) => {
            const conditionType = row.querySelector('[data-facet-type]')?.value || '';
            const operator = row.querySelector('[data-facet-operator]')?.value || '';
            const referenceCode = row.querySelector('[data-facet-reference-code]')?.value || '';
            const value = row.querySelector('[data-facet-value]')?.value || '';

            if (!conditionType || !operator) {
                return;
            }

            payload.push({
                conditionType,
                operator,
                referenceCode: referenceCode || null,
                value: value || null,
                position: index,
            });
        });

        return payload;
    }

    renderPreviewProducts(products, count, limit) {
        if (!this.hasPreviewListTarget) {
            return;
        }

        this.clearPreviewList();

        if (!products.length) {
            const item = document.createElement('li');
            item.className = 'list-group-item text-muted';
            item.textContent = this.message('empty');
            this.previewListTarget.appendChild(item);
            return;
        }

        products.forEach((product) => {
            const item = document.createElement('li');
            item.className = 'list-group-item d-flex justify-content-between align-items-center';

            const name = document.createElement('span');
            name.textContent = product.name || this.message('unnamed');
            item.appendChild(name);

            const badge = document.createElement('span');
            badge.className = 'badge bg-secondary-lt text-secondary';
            badge.textContent = product.code || '#';
            item.appendChild(badge);

            const link = document.createElement('a');
            link.className = 'btn btn-md btn-primary';
            link.textContent = this.message('view');
            link.target = '_blank';
            link.rel = 'noopener';
            link.href = product.url;
            item.appendChild(link);

            this.previewListTarget.appendChild(item);
        });

        if (count > limit) {
            const more = document.createElement('li');
            more.className = 'list-group-item text-muted small';
            more.textContent = this.message('more', count - limit);
            this.previewListTarget.appendChild(more);
        }
    }

    setPreviewLoading(isLoading) {
        if (!this.hasPreviewLoadingTarget) {
            return;
        }

        this.previewLoadingTarget.classList.toggle('d-none', !isLoading);
    }

    clearPreviewList() {
        if (this.hasPreviewListTarget) {
            this.previewListTarget.innerHTML = '';
        }
    }

    renderPreviewSummary(message) {
        if (this.hasPreviewSummaryTarget) {
            this.previewSummaryTarget.textContent = message;
        }
    }

    renderPreviewError(message) {
        if (!this.hasPreviewErrorTarget) {
            return;
        }

        this.previewErrorTarget.textContent = message;
        this.previewErrorTarget.classList.remove('d-none');
    }

    clearPreviewError() {
        if (!this.hasPreviewErrorTarget) {
            return;
        }

        this.previewErrorTarget.textContent = '';
        this.previewErrorTarget.classList.add('d-none');
    }
}
