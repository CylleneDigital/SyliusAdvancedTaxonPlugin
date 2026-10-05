import './scss/mega-menu.scss';
import './scss/advanced-taxon-shop.scss';

import { Application } from '@hotwired/stimulus';
import MobileMenuController from './js/mobile-menu-controller.js';
import MegaMenuController from './js/mega-menu-controller.js';
import FeaturedProductsSliderController from './js/featured-products-slider-controller.js';
import AdvancedFiltersController from './js/advanced-filters-controller.js';
import UniverseSliderController from './js/universe-slider-controller.js';
import FacetSearchController from './js/facet-search-controller.js';

const app = Application.start();
app.register('at-mobile-menu', MobileMenuController);
app.register('at-mega-menu', MegaMenuController);
app.register('at-featured-slider', FeaturedProductsSliderController);
app.register('at-advanced-filters', AdvancedFiltersController);
app.register('at-universe-slider', UniverseSliderController);
app.register('at-facet-search', FacetSearchController);

