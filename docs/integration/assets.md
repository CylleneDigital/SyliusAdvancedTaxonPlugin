# Assets

The plugin ships two Webpack Encore entrypoints, one for the admin and one for the shop. Its
templates load no script or stylesheet by themselves: the application bundles the entrypoints with
its own assets.

## Import the entrypoints

```js
// assets/admin/entrypoint.js
import '../../vendor/cyllene-digital/sylius-advanced-taxon-plugin/assets/admin/entrypoint.js';
```

```js
// assets/shop/entrypoint.js
import '../../vendor/cyllene-digital/sylius-advanced-taxon-plugin/assets/shop/entrypoint.js';
```

Then build:

```bash
yarn install
yarn build
```

`yarn encore dev --watch` while developing, `yarn encore production` for production, as for the
rest of a Sylius application.

Without the import, the plugin pages render unstyled, and every interaction listed below stays
inert (icon picker, conditions, collections, sliders, filters panel, mobile menu).

## Content

| Entrypoint | Styles | Stimulus controllers |
|---|---|---|
| `assets/admin/entrypoint.js` | `assets/admin/scss/admin-taxon.scss` | `at-admin-taxon-icon-picker`, `at-admin-taxon-facet-conditions`, `at-admin-form-collection`, `at-admin-taxon-form-errors` |
| `assets/shop/entrypoint.js` | `assets/shop/scss/mega-menu.scss`, `assets/shop/scss/advanced-taxon-shop.scss` | `at-mobile-menu`, `at-mega-menu`, `at-featured-slider`, `at-advanced-filters`, `at-universe-slider`, `at-facet-search` |

### Admin controllers

| Identifier | Source | Role | Used in |
|---|---|---|---|
| `at-admin-taxon-icon-picker` | `assets/admin/js/taxon-icon-picker-controller.js` | Switches between glyph and uploaded pictogram, opens the icon picker modal, selects an icon | `Advanced customizations` tab (`display.html.twig`) |
| `at-admin-taxon-facet-conditions` | `assets/admin/js/taxon-facet-conditions-controller.js` | Adds and removes condition rows, fills the reference choices of each condition type, posts the preview (CSRF token) to `cyllene_digital_sylius_advanced_taxon_admin_facet_preview` | `Mass-assignment conditions` tab |
| `at-admin-form-collection` | `assets/admin/js/form-collection-controller.js` | Adds rows to form collections (media zones), with an index that never reuses one already rendered | `collection_theme.html.twig` |
| `at-admin-taxon-form-errors` | `assets/admin/js/taxon-form-errors-controller.js` | After a refused submission, flags the tabs holding an error, shows the first one and unfolds the zones holding an error | `form_with_tabs.html.twig` |

`assets/admin/js/collection-index.js` is a helper shared by `at-admin-form-collection` and `at-admin-taxon-facet-conditions`: the next row index, never one already rendered.
`assets/admin/js/side-column-modals.js` moves a modal opened from the sticky left column of the
taxon form (the delete confirmation of the taxon tree) under `<body>`, so that it shows above the
page backdrop.

### Shop controllers

| Identifier | Source | Role | Used in |
|---|---|---|---|
| `at-mega-menu` | `assets/shop/js/mega-menu-controller.js` | Shows the image of the hovered featured taxon or second-level column in a mega menu panel | Mega menu (`navbar/menu.html.twig`) |
| `at-mobile-menu` | `assets/shop/js/mobile-menu-controller.js` | Drill-down drawer of the mobile mega menu, opened by `#at-mn-burger` | Mega menu, `taxon_hamburger.html.twig` |
| `at-featured-slider` | `assets/shop/js/featured-products-slider-controller.js` | Previous / next scrolling of the featured products slider | `advanced_taxon_featured_products/slider.html.twig` |
| `at-advanced-filters` | `assets/shop/js/advanced-filters-controller.js` | `trigger` mode: the `All filters` button. `panel` mode: opens and closes the filters panel (off-canvas below 992px, Escape closes it) | `all_filters.html.twig`, `facet_filters.html.twig` |
| `at-facet-search` | `assets/shop/js/facet-search-controller.js` | Filters the values of one facet list as the visitor types; hidden values stay submitted | `facet_filters.html.twig` |
| `at-universe-slider` | `assets/shop/js/universe-slider-controller.js` | Universe hero slider and **Slider** mode of the top and bottom zones: autoplay every 5 s (`interval` value), paused on hover, previous / next | `media/slider.html.twig` |

## A separate Stimulus application

Each entrypoint calls `Application.start()` from `@hotwired/stimulus` and registers its controllers
on that application. It does not use `@symfony/stimulus-bridge` nor the `controllers.json` of the
application, and ships none.

Two Stimulus applications on the same page do not conflict: each one only connects the identifiers
it registered. All plugin identifiers start with `at-`. Do not register controllers with an `at-`
identifier in your application.

## Build requirements

| Requirement | Why | In a Sylius 2 application |
|---|---|---|
| `@hotwired/stimulus` | Imported by every controller | Dependency of `@sylius-ui/admin` and `@sylius-ui/shop` |
| `sass` and `sass-loader`, Encore `.enableSassLoader()` | The entrypoints import `.scss` files | `sass` and `sass-loader` are dependencies of `@sylius-ui/*`; the Encore configuration that compiles your entrypoints must enable the Sass loader |
| Bootstrap 5 JavaScript | Admin tabs (`data-bs-toggle="tab"`), shop offcanvas when the mega menu is off | Loaded by the Sylius admin and shop |

The plugin has no `package.json`: there is nothing to add to yours.

## Icons

The templates call `ux_icon()` (Symfony UX Icons, required by Sylius). Sylius registers the `tabler`
set from local SVG files, enables the Iconify fallback and ignores icons it cannot find. Every icon
of the plugin templates is a local file: nothing is fetched at render time.

| Prefix | Source at runtime |
|---|---|
| `tabler:` | Local files of `SyliusUiBundle` |
| `at:` (plugin templates) | Local files of the plugin, `assets/icons/`: Tabler icons Sylius does not ship (MIT, licence next to them) |
| Prefixes of your `icon_libraries` | Iconify API, unless imported locally or a local set |

The plugin registers its `at` set by prepending `ux_icons.icon_sets`. Its own prefix never shadows a
set of your application.

Only the icons chosen by a merchant can come from Iconify: a taxon icon stored in the database, or
an icon of your `icon_libraries`, with a prefix that is not a local set. Such an icon needs network
access from the server on first render, then lives in the cache. To serve them locally, import them
once and commit the result:

```bash
bin/console ux:icons:import ph:star ph:heart ph:tree-evergreen
```

## Images

Pictograms and media are served through Liip Imagine filter sets, not through the entrypoints:
see [Configuration](configuration.md#image-filter-sets-liip-imagine).
