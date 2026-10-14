# CLAUDE.md

Guide for coding agents working on this repository. It complements, without repeating them:

- [`README.md`](README.md): what the plugin does, installation, production notes
- [`CONTRIBUTING.md`](CONTRIBUTING.md): bringing up the environment, QA commands, PR conventions
- [`docs/`](docs/): documentation by audience ([`docs/README.md`](docs/README.md) is the index, [`docs/development/code-map.md`](docs/development/code-map.md) maps the code)

This file only holds what reading the code will not tell you.

The plugin targets Sylius 2.1, 2.2 and 2.3, Symfony 7.4 and 8 (8 with Sylius 2.3 only), PHP 8.3+.

## Where things live

| Path | Role |
|---|---|
| `src/Entity/` | `AdvancedTaxon*`, `MegaMenuChannel*` and `AdvancedTaxonImage*` **interfaces + traits** used by the application entities, plus the plugin's own entities (`FacetCondition`, translations) |
| `src/Service/ConditionalTaxonQueryBuilder.php`, `FacetProductQueryBuilder.php` | The conditional taxon queries (and the admin preview); the storefront advanced filters and facets |
| `src/Grid/` | Takes over the query of the Sylius shop product grid (`sylius.grid.shop_product` event) |
| `src/EventListener/`, `src/Message*/` | Conditional taxon synchronization: collected on flush, handled through Messenger |
| `src/Validator/` | Facet condition and "no universe as main taxon" constraints |
| `config/services/*.php` | Service definitions, one file per area, PHP only |
| `config/twig_hooks/` | Admin and shop hooks, some of them **overriding Sylius core hooks** |
| `assets/` | Admin and shop entrypoints (Stimulus controllers, SCSS) and the `at` icon set (`assets/icons/`, Tabler SVGs) |
| `src/Migrations/` | The plugin's single migration, written against the DBAL `Schema` API |
| `tests/TestApplication/src/Entity/` | The `Taxon`, `Channel` and `TaxonImage` entities of the test application, using the traits |
| `tests/Unit/`, `tests/Integration/`, `tests/Functional/` | No kernel / kernel and real database (transaction rolled back) / back office over HTTP |
| `features/`, `tests/Behat/` | Behat scenarios, contexts (`Setup`, `Ui/Admin`, `Ui/Shop`, `Domain`), pages and elements; configuration in `behat.dist.php`, one suite per feature in `tests/Behat/Resources/suites.php` |
| `tests/TestApplication/` | Throwaway Sylius application: the entities using the traits, test and dev configuration; its `templates/` directory must exist (Twig path) |

## Invariants

Each rule is here in full, with the names the code uses; its reasons are in the decision record it
links ([`docs/adr/`](docs/adr/README.md)). Read the record before changing a rule, and write a new
record rather than editing an accepted one - the records hold no path nor figure, this file does.

- **Never replace a Sylius model.** The application owns `Taxon`, `Channel` and `TaxonImage`; the
  plugin only ships traits (mapping as attributes) and interfaces. Relations target the Sylius
  interfaces (`TaxonInterface`, `ProductInterface`, `TaxonImageInterface`), resolved by
  `resolve_target_entities`. Code tests `instanceof AdvancedTaxonInterface`, never a class
  ([0001](docs/adr/0001-never-replace-a-sylius-model.md)).
- **The taxon listing is the Sylius grid.** `ShopProductListQueryBuilder` calls
  `createShopListQueryBuilder()` and adds the children products and the advanced filters: no
  parallel product query in templates. Facet counts come from `createBaseProductsQb()`, which must
  select exactly what the grid lists ([0006](docs/adr/0006-taxon-listing-is-the-sylius-grid.md),
  [0007](docs/adr/0007-facet-counts-start-from-the-grid-query.md)).
- **Conditional taxons fail closed.** A stored condition that is not applicable (wrong operator,
  missing reference or value) makes the taxon match nothing, never the whole catalog
  ([0015](docs/adr/0015-non-applicable-condition-matches-nothing.md)).
- **No LIKE on JSON columns** (not portable to PostgreSQL): select attribute values are resolved in
  PHP, then filtered by identifier ([0009](docs/adr/0009-no-like-on-json-columns.md)).
- **Services are explicit**: one file per area in `config/services/`, ids prefixed with
  `cyllene_digital_sylius_advanced_taxon.` (the controller keeps its class as id), no autowiring nor
  autoconfiguration. A class implementing `ResetInterface` needs the
  `kernel.reset` tag by hand ([0028](docs/adr/0028-explicit-services-no-autowiring.md),
  [0029](docs/adr/0029-per-request-memory-services-are-reset.md)).
- **Template icons are local**: `tabler:` when Sylius ships the icon, otherwise a Tabler SVG added to
  `assets/icons/` and used as `at:<name>` (Sylius ships 82 Tabler icons only, any other is fetched
  from Iconify at render time). A unit test fails on an icon written in a template that no local set
  ships
  ([0021](docs/adr/0021-template-icons-are-local.md)).
- **Twig hooks are imported, not prepended**: `config/config.yaml` must be loaded after the Sylius
  configuration, since a prepended hook loses against the Sylius core hooks it overrides
  ([0004](docs/adr/0004-twig-hooks-imported-not-prepended.md)).
- **Pictograms are Sylius images** (`image:<path>` in the icon field, Sylius image uploader and
  storage, Liip filter `cyllene_advanced_taxon_icon`). Values rendered in `style` or `href`
  attributes are sanitized on write (`AdvancedTaxonValueSanitizer`)
  ([0019](docs/adr/0019-pictograms-are-sylius-images.md), [0020](docs/adr/0020-values-sanitized-on-write.md)).

- **A schema change is a new migration** in `src/Migrations/` (DBAL `Schema` API): `v1.0.0` shipped
  `Version20260828173000`, which is never edited again. Free of drift on MySQL, MariaDB and
  PostgreSQL (CI runs `down()` / `up()` then `tests/check-schema-drift.sh`, which lists the columns
  added to the Sylius tables: keep it in step with the migrations)
  ([0031](docs/adr/0031-schema-change-is-a-new-migration.md)).
- The traits, interfaces, Twig hooks and functions, bundle configuration, message, command, admin
  route and schema are the public contract ([`docs/architecture/public-contract.md`](docs/architecture/public-contract.md)):
  they only break in a major version, with an entry in `UPGRADE.md`.
- **No `CHANGELOG.md`**: the GitHub release notes are the changelog, `UPGRADE.md` says what a shop
  has to do.

## Flex recipe

The recipe is not in this repository: it lives in `symfony/recipes-contrib`, under
`cyllene-digital/sylius-advanced-taxon-plugin/<major.minor>/`. It must follow the plugin: update it
when the bundle class, `config/config.yaml`, `config/routes.yaml`, the bundle configuration or an
installation step changes, and add a new version directory when a release changes what has to be
installed. `docs/integration/installation.md` and the README `Installation` section describe it.
The recipes-contrib checks reject an empty root key and any `: ~`, even in a comment: keep the
optional settings commented out with `null`, never `~`.

## Commands

The QA commands are in [`CONTRIBUTING.md`](CONTRIBUTING.md). What it does not say:

- The console is the test app's proxy: `vendor/bin/console` (no `bin/console` at the root).
- The public directory served by the test app is `vendor/sylius/test-application/public`.
- `composer.lock` is not versioned: CI resolves each job with its real PHP, Sylius and Symfony
  versions.
- Any Behat run needs a Chrome with remote debugging on `127.0.0.1:9222` (the driver asks for its
  version at startup, even with `--tags='~@javascript'`); the `@javascript` scenarios also need the
  test application served **in the test environment** at `BEHAT_BASE_URL`.
- The integration, functional and Behat suites need the migrated test database.
- Code coverage is only measured in CI, on the Sylius 2.3 / PHP 8.4 / Symfony 7.4 / MySQL 8.4 job
  (pcov).

## Known pitfalls

- **On MariaDB with DBAL 4**, the Sylius core migrations are marked as executed but skipped unless
  the server version is a MySQL one: the symptom is a schema holding only the
  `cyllene_advanced_taxon_*` tables. The build action declares MariaDB as such
  (`serverVersion=11.4.0-MariaDB`): the CI MariaDB jobs build the schema from the mappings
  (`database_setup: schema`), and the `down()` / `up()` replay is their only run of the plugin
  migration.
- **Sylius 2.1 mappings fail `doctrine/orm` 3.7 validation** (`ShipmentUnit#shipment`): CI caps the
  ORM below 3.7 before Sylius 2.3, the plugin's own constraint stays open
  ([0032](docs/adr/0032-no-dependency-cap-for-an-upstream-bug.md)).
- **Behat**: the Sylius suites are not imported (2.1 / 2.2 ship them in YAML, 2.3 in PHP); the plugin
  suites only use the Sylius contexts, which are services. The plugin contexts use PHP attributes (`#[Given]`, `#[AfterScenario]`):
  Behat 4, the one Symfony 8 needs, no longer reads annotations; the CI keeps Behat 3 before Sylius 2.3,
  whose contexts are annotated. The Behat dependencies are the maintained
  ones (`behat/mink`, `behat/mink-browserkit-driver` 2.x): the abandoned `friends-of-behat` forks
  emit PHP 8.5 deprecations that Behat turns into failures.
- **The test application lists the products of the child taxons natively**
  (`sylius_shop.product_grid.include_all_descendants: true`): the plugin option doing the same is
  covered by the integration tests of the grid query, not by Behat.
- **A browser scenario saves in another process**: a context refreshes an entity before asserting
  on it, or it reads the stale one from the identity map.
- **The taxon form template renders `form._token` itself** (`form_with_tabs.html.twig`): with
  `render_rest` off, nothing else does on Sylius 2.1.
- `sylius:debug:twig-hooks` exists from `sylius/twig-hooks` 0.12, Sylius 2.3 only.
- **`symfony/ux-twig-component` is kept below 3.5 in `require-dev`**: 3.5 lets an explicit `null`
  override a `{% props %}` default, and the Sylius grid delete action passes a null label and icon
  to `sylius_admin:delete_modal` (empty delete buttons in every admin grid). Drop the constraint
  once Sylius passes defaults itself ([Sylius#19278](https://github.com/Sylius/Sylius/issues/19278)).
  An upstream workaround never goes into the plugin's own constraints
  ([0032](docs/adr/0032-no-dependency-cap-for-an-upstream-bug.md)).
- `sylius_test_html_attribute()` attributes (`data-test-*`) are what the Behat pages target: keep
  them when reworking a shop template.

## Keeping the docs in step

The same facts live in several places; change one, change them all:

- the CI matrix and steps: `.github/workflows/build.yaml`, `CONTRIBUTING.md`, `docs/testing/tests.md`,
  the README `Status` section and `docs/architecture/public-contract.md` (support policy);
- the compatibility (Sylius, Symfony, PHP): `composer.json`, the README, `docs/integration/installation.md`,
  `docs/architecture/overview.md` and the banner `docs/assets/banner.svg`;
- the back-office labels: `translations/messages.en.yaml` and the bold labels of `docs/admin/user-guide.md`;
- the Twig hooks: `config/twig_hooks/*.yaml` and `docs/integration/theming.md`;
- the Twig functions: `src/Twig/*.php`, `docs/shop/twig-functions.md` and `docs/architecture/public-contract.md`;
- the Behat features: `features/`, `tests/Behat/Resources/suites.php` and `docs/testing/tests.md`;
- a decision taken or reversed: a new record in `docs/adr/`, its line in `docs/adr/README.md`, and
  the link from the rule here;
- what a screen looks like: the screenshots in `docs/assets/` after a visible change.

## What does not belong here

Shop-specific merchandising, theme styling and project data stay in the project using the plugin.
Maintainer procedures (release, local stack, upstream watch) stay out of the repository.
