# Code map

Where each part of the plugin lives, for contributors. How the parts fit together is in
[Architecture overview](../architecture/overview.md); the checks a pull request has to pass are in
[CONTRIBUTING.md](../../CONTRIBUTING.md).

## Domain model and persistence

| Path | Role |
|---|---|
| `src/Entity/AdvancedTaxonInterface.php`, `AdvancedTaxonTrait.php` | Taxon fields, mapping as attributes, used by the application `Taxon` entity |
| `src/Entity/MegaMenuChannelInterface.php`, `MegaMenuChannelTrait.php` | Channel mega menu toggle |
| `src/Entity/AdvancedTaxonImageInterface.php`, `AdvancedTaxonImageTrait.php` | Taxon media fields and translations |
| `src/Entity/AdvancedTaxonValueSanitizer.php` | Sanitization on write: color, icon, media link |
| `src/Entity/FacetCondition.php` | A condition of a conditional taxon (types, operators) |
| `src/Entity/TaxonFeaturedItemsTranslation.php`, `TaxonImageTranslation.php` | Translated texts of the featured blocks and of the media |
| `src/Migrations/` | The single plugin migration ([Migrations](../persistence/migrations.md)) |
| `tests/TestApplication/src/Entity/` | The test application entities using the traits: the reference integration |

## Storefront

| Path | Role |
|---|---|
| `src/Grid/ShopProductGridListener.php` | Routes the query of the Sylius shop product grid to the plugin |
| `src/Grid/ShopProductListQueryBuilder.php` | Native grid query + children products + advanced filters |
| `src/Service/FacetProductQueryBuilder.php` | Storefront advanced filters and facet counts |
| `src/Service/SelectAttributeValueResolver.php` | Select attribute choices matched in PHP (JSON column), cached for the request |
| `src/Service/DqlExpressions.php` | LIKE pattern and inlined id list shared by both query builders |
| `src/Twig/AdvancedTaxonExtension.php` | Declares every plugin Twig function, each served by a runtime |
| `src/Twig/Runtime/FacetRuntime.php` | Facet data, current taxon, condition references |
| `src/Twig/Runtime/UniverseRuntime.php` | Universe page data and media URLs (Liip Imagine) |
| `src/Twig/Runtime/IconLibrariesRuntime.php` | Icon libraries of the icon picker |
| `src/Twig/Runtime/MenuRuntime.php` | Grouped preloading of the menu subtree, children ordered with the featured ones first |
| `src/Twig/Runtime/VisibilityRuntime.php` | Featured products and children filtered for the storefront: enabled, in the current channel (one query for the channels not loaded yet) |
| `templates/shop/product/index/content/` | Taxon page: listing, sidebar, featured blocks, media, universe |
| `templates/bundles/SyliusShopBundle/` | Replacements of Sylius shop templates, wired through Twig hooks (not automatic overrides) |
| `templates/components/advanced_taxon/` | `taxon_label` (color, icon, pictogram) and the child taxon card |
| `config/twig_hooks/shop.yaml` | Storefront hooks ([Theming](../integration/theming.md)) |

## Back office

| Path | Role |
|---|---|
| `src/Form/Extension/TaxonTypeExtension.php` | Listing, featured items, universe and condition fields of the taxon form |
| `src/Form/Extension/TaxonAppearanceTypeExtension.php` | Color, icon, pictogram upload and their visibility |
| `src/Form/Extension/TaxonMediaZonesTypeExtension.php` | Media zones in place of the Sylius images collection |
| `src/Form/Extension/ChannelTypeExtension.php` | Channel mega menu toggle |
| `src/Form/Type/` | Conditions, media, translations, autocompletes |
| `src/Controller/AdminFacetPreviewController.php` | Preview of the products matching the conditions |
| `config/routes.yaml`, `config/routes/admin.yaml` | Its route, under the back-office prefix |
| `src/Validator/Constraints/` | `ValidFacetCondition`, `NotUniverseMainTaxon` (`config/validation/Product.xml`) |
| `src/Service/TaxonIconUploader.php`, `UploadedImageResizer.php` | Pictogram storage, media downscaling |
| `src/EventListener/TaxonIconRemovalListener.php` | Removes the pictogram file a taxon no longer uses (taxon deleted, icon replaced), once the change is flushed |
| `templates/admin/` | Taxon form tabs and sections, channel toggle |
| `config/twig_hooks/admin.yaml` | Back office hooks |

## Conditional taxons

| Path | Role |
|---|---|
| `src/EventListener/ConditionalTaxonSyncListener.php` | Collects the taxons whose conditions changed (`onFlush`, ids in `postFlush`), dispatches the message once the request, command or handled message is over |
| `src/Message/SynchronizeConditionalTaxon.php`, `src/MessageHandler/` | Message and handler, synchronous unless routed |
| `src/Service/ConditionalTaxonQueryBuilder.php` | Query of the products matching the conditions of a taxon, admin preview |
| `src/Service/ConditionalTaxonProductAssigner.php` | Differential synchronization (`ConditionalTaxonSynchronizerInterface`) |
| `src/Service/FacetReferenceChecker.php`, `FacetReferenceCheckerInterface.php` | Whether the attribute, option or taxon of a condition exists, for the validator and the queries |
| `src/Command/SyncConditionalTaxonsCommand.php` | Full synchronization, to schedule |

The synchronizer never flushes while synchronizing a single taxon: the `doctrine_transaction`
middleware of `sylius.command_bus` does. A full synchronization flushes and clears the entity manager after each batch of 50 taxons.

## Wiring

- Services are defined explicitly, without autowiring nor autoconfiguration, as Symfony recommends
  for reusable bundles: `config/services.php` imports one file per area from `config/services/`
  (`facet`, `grid`, `conditional_taxon`, `media`, `form`, `twig`, `controller`). Ids are prefixed with
  `cyllene_digital_sylius_advanced_taxon.` and private, except the controller, whose id is its class
  (the route refers to it); tags (`twig.extension`, `form.type_extension`,
  `doctrine.event_listener`, `messenger.message_handler`, `kernel.reset`...) are set there too.
- Public: the grid query builder id and the controller. The interfaces
  `ConditionalTaxonSynchronizerInterface` and `FacetReferenceCheckerInterface` are aliased to their
  service, which lets an application decorate them; they stay internal (not covered by semver).
- A new service gets its definition in the matching file; a class implementing `ResetInterface`
  also gets the `kernel.reset` tag. The classes only keep the attributes that carry runtime
  metadata: `#[AsCommand]`, `#[AsEntityAutocompleteField]` (alias and route), `#[IsGranted]`.
- `src/DependencyInjection/CylleneDigitalSyliusAdvancedTaxonExtension.php` prepends the Doctrine
  mapping, the migrations, the Liip Imagine filter sets and the `at` UX Icons set (`assets/icons/`). The Twig hooks are imported through
  `config/config.yaml`, not prepended: they override Sylius core hooks.

## Front-end assets

| Entrypoint | Stimulus controllers |
|---|---|
| `assets/admin/entrypoint.js` | `at-admin-taxon-icon-picker`, `at-admin-taxon-facet-conditions`, `at-admin-form-collection`, `at-admin-taxon-form-errors` |
| `assets/shop/entrypoint.js` | `at-mobile-menu`, `at-mega-menu`, `at-featured-slider`, `at-advanced-filters`, `at-universe-slider`, `at-facet-search` |

Conventions:

- No inline script in templates: a behaviour is a Stimulus controller, registered in its entrypoint
  with the `at-` prefix.
- Texts shown by a controller come from the translations, passed as a Stimulus value.
- Static layout rules live in the SCSS files under `assets/*/scss/`.
- A template icon is `tabler:` when Sylius ships it, otherwise a Tabler SVG added to `assets/icons/`
  and used as `at:<name>`; a test fails on any other prefix.
- A row added to a form collection takes its index from `assets/admin/js/collection-index.js`.
