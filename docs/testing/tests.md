# Tests

The commands the CI runs are in [CONTRIBUTING.md](../../CONTRIBUTING.md#what-has-to-pass-before-a-pull-request).

| Suite | Directory | Needs | What it covers |
|---|---|---|---|
| Unit | `tests/Unit/` | nothing | Entities and sanitizers, form extensions, uploaders, validators, Twig runtimes (visibility, universe, menu ordering), DI extension and service definitions (reset tags) |
| Integration | `tests/Integration/` | kernel, test database | Storefront queries (every condition type and operator, facets and their selection, channel and locale, filter normalization and caps, grid pagination), synchronization and its order, its trigger at the end of a console command, autocompletes, menu and universe preloading (query count), visibility of featured products, taxon form submissions, pictogram removal, reset of the cached services |
| Functional | `tests/Functional/` | kernel, test database | Back office over HTTP: taxon form, conditions preview endpoint and its limits |
| Behat | `features/`, `tests/Behat/` | kernel, test database, Chrome for `@javascript` | Back office forms, storefront pages and menus, conditional taxon synchronization |

`vendor/bin/phpunit` runs the default suite `all` (every test once); `--testsuite=unit`,
`integration`, `functional` or `non-unit` runs a part of it (`phpunit.xml.dist`).

PHPStan (level `max`) covers `src/` and every test directory, the test application entities included.

Code coverage is measured by the CI only, on its primary job (pcov), and shown in the job summary;
`phpunit.xml.dist` limits it to `src/`. The CI matrix is described in
[CONTRIBUTING.md](../../CONTRIBUTING.md#what-has-to-pass-before-a-pull-request).

## Integration and functional tests

- Integration tests run inside a transaction rolled back at the end of the test: the records built by
  `tests/Integration/CatalogBuilder.php` (locales, channels, taxons, products, variants, options,
  attributes) never reach other tests, and no test relies on records left by another one (the taxon
  form tests create the locale they use).
- Functional tests extend `tests/Functional/AdminTestCase.php`: a logged-in back office client on a
  kernel that is not rebooted between requests, with records created under unique codes and removed
  after the test.
- The test application never fetches icons from iconify.design in the `test` environment
  (`tests/TestApplication/config/packages/ux_icons.yaml`): only the local icon sets are read.
- Files written to the image storage or the temporary directory are removed after the test or the
  scenario, even when it fails.
- Storefront queries are tested on the real database, including the pagination of the grid query
  through Pagerfanta with the options the Sylius grid uses (fetch join collection, output walkers).

## Behat

Laid out like the other Cyllene plugins and Sylius itself:

| Path | Content |
|---|---|
| `features/admin/`, `features/shop/`, `features/taxon/` | One feature per business capability, tagged with its own name |
| `behat.dist.php`, `tests/Behat/Resources/suites.php` | Configuration in PHP; one suite per feature, `ui_<feature>` (tags `@<feature>&&@ui`) or `domain_<feature>` (`@<feature>&&@domain`) |
| `tests/Behat/Context/Setup/` | `Given` steps putting taxons and channels in a state without the forms |
| `tests/Behat/Context/Ui/Admin/`, `Ui/Shop/` | `When` / `Then` steps through the pages and elements |
| `tests/Behat/Context/Domain/` | Conditional taxon materialization, without a browser (Doctrine flush and console command) |
| `tests/Behat/Page/`, `tests/Behat/Element/` | Page objects: the shop taxon page, the plugin part of the admin taxon and channel forms, the shop menu |
| `tests/Behat/Resources/services.php` | Pages, elements and contexts, imported by `tests/TestApplication/config/services_test.php` in the `test` environment; contexts are public since Behat resolves them from the container |

The Sylius steps are reused wherever they exist (channels, taxonomy, products, attributes, opening
and saving a form, notifications), with the Sylius transformers (`"Watches" taxon`, `"Gold watch"
product`, `this channel`). The shop templates carry `data-test-*` attributes
(`sylius_test_html_attribute()`, rendered in the test environment only) that the page objects target.

| Feature | What it covers |
|---|---|
| `admin/customizing_taxon_appearance` | Color and icon, pictogram upload, icon picker (`@javascript`) |
| `admin/managing_taxon_media` | Card text option and destination URL per zone, position pre-fill, main image zone listing the Sylius image without type, rows added after a removal (`@javascript`) |
| `admin/configuring_taxon_pages` | Featured products display, universe page |
| `admin/choosing_the_main_taxon_of_a_product` | A universe refused as main taxon |
| `admin/managing_conditional_taxons` | Conditions preview, conditions saved and products assigned, a condition without its attribute refused and its tab flagged (`@javascript`) |
| `admin/enabling_the_mega_menu` | Channel mega menu toggle |
| `shop/browsing_taxon_pages` | Featured child taxons and products, media zones (top, left, bottom, slider mode), main image, media cards in the list, at the start or among the products, and in the list view, name with color and icon |
| `shop/filtering_products_with_advanced_filters` | Filter values and counts, filtering, filters kept by the grid search, filters only when enabled |
| `shop/browsing_universe_pages` | Universe title, child taxons instead of a product list, featured products of the children |
| `shop/navigating_with_the_mega_menu` | Mega menu per channel, featured categories, mobile menu button, color in the menu |
| `taxon/materializing_conditional_taxons` | Conditions added, edited and removed, incomplete condition (fail closed), renaming, disabled products, deletion, command |

```bash
vendor/bin/behat --strict --no-interaction -f progress
vendor/bin/behat --suite=domain_materializing_conditional_taxons
vendor/bin/behat --strict --no-interaction -f progress --tags='~@javascript'
```

The `doctrine_orm` hook purges the database before each scenario; it does not create the schema, so
the test database has to exist and be migrated first.

The `@javascript` scenarios run in Chrome against the application served at `BEHAT_BASE_URL`
([CONTRIBUTING](../../CONTRIBUTING.md#behat)). The form fields live in Bootstrap tabs and accordions:
the admin form element opens them first, since the browser only interacts with visible fields. The
browser saves in another process, so the contexts refresh an entity before asserting on it.

The test application sets `sylius_shop.product_grid.include_all_descendants` to `true`: its taxon
pages always list the products of the child taxons, so the plugin option that does the same is
covered by the integration tests of the grid query, not by Behat.
