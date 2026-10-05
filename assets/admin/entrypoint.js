import { Application } from '@hotwired/stimulus';
import TaxonIconPickerController from './js/taxon-icon-picker-controller.js';
import TaxonFacetConditionsController from './js/taxon-facet-conditions-controller.js';
import FormCollectionController from './js/form-collection-controller.js';
import TaxonFormErrorsController from './js/taxon-form-errors-controller.js';
import './js/side-column-modals.js';
import './scss/admin-taxon.scss';

const app = Application.start();
app.register('at-admin-taxon-icon-picker', TaxonIconPickerController);
app.register('at-admin-taxon-facet-conditions', TaxonFacetConditionsController);
app.register('at-admin-form-collection', FormCollectionController);
app.register('at-admin-taxon-form-errors', TaxonFormErrorsController);
