# Architecture overview

## Plugin purpose

The plugin adds editorial and merchandising layers on top of Sylius 2 taxons: colors, icons and
pictograms, featured children and products, media zones, universe pages, storefront facet filters,
conditional (rule driven) product assignment, and a per-channel mega menu. Feature tour:
[Features](../features.md).

**Core principle:** the plugin never replaces a Sylius model. The application keeps its own `Taxon`,
`Channel` and `TaxonImage` entities and adds the plugin fields through traits and interfaces
([Installation](../integration/installation.md)). Everything else (conditions, translations, join
tables) lives in plugin-owned `cyllene_advanced_taxon_*` tables.

## Technical stack

| Component | Version (`composer.json`) |
|-----------|---------|
| PHP | `^8.3` (`ext-gd` required) |
| Sylius | `^2.1` (CI: `~2.1.0`, `~2.2.0`, `~2.3.0`) |
| Symfony | `^7.4 \|\| ^8.0` (8 needs PHP 8.4, CI runs it with Sylius 2.3) |
| `sylius/twig-hooks` | `^0.8 \|\| ^0.9 \|\| ^0.12 \|\| ^0.14` (the one of the Sylius version) |
| `liip/imagine-bundle` | `^2.15` |
| `symfony/ux-autocomplete`, `symfony/ux-icons` | `^2.36 \|\| ^3.0` |
| `symfony/messenger` | `^7.4 \|\| ^8.0` |
| `doctrine/doctrine-migrations-bundle` | `^3.7` |

The Sylius grid and the Sylius image uploader come with `sylius/sylius`. The shop templates render
glyphs with `ux_icon()` (Symfony UX Icons).

## Code layers (`src/`)

Namespace root: `CylleneDigital\SyliusAdvancedTaxonPlugin\`.

```
src/
├── Entity/                 # Traits + interfaces for the Sylius models, plugin entities, sanitizer
├── Service/                # Facet queries, condition references, conditional synchronization, pictogram upload, image resize
├── Grid/                   # Takeover of the Sylius shop product grid query
├── EventListener/          # Doctrine listeners: conditional taxon sync, pictogram file removal
├── Message/                # SynchronizeConditionalTaxon
├── MessageHandler/         # SynchronizeConditionalTaxonHandler
├── Validator/Constraints/  # ValidFacetCondition, NotUniverseMainTaxon (+ validators)
├── Form/                   # Taxon / channel form extensions, condition, media and translation types
├── Twig/                   # AdvancedTaxonExtension and its runtimes (facet, icon libraries, menu, universe, visibility)
├── Controller/             # AdminFacetPreviewController (JSON preview of conditions)
├── Command/                # cyllene:advanced-taxon:sync-conditional-taxons
├── Migrations/             # Version20260828173000 (single migration)
└── DependencyInjection/    # Configuration (icon_libraries), extension (load + prepend)
```

| Layer | Classes | Role |
|-------|---------|------|
| Entity (traits / interfaces) | `AdvancedTaxonTrait` + `AdvancedTaxonInterface`, `MegaMenuChannelTrait` + `MegaMenuChannelInterface`, `AdvancedTaxonImageTrait` + `AdvancedTaxonImageInterface` | Columns and relations added to `sylius_taxon`, `sylius_channel`, `sylius_taxon_image` |
| Entity (own) | `FacetCondition`, `TaxonFeaturedItemsTranslation`, `TaxonImageTranslation` | Plugin tables; mapping registered by the DI extension (attributes, prefix `…\Entity`) |
| Entity (helper) | `AdvancedTaxonValueSanitizer` | Write-time sanitization of color, icon and media URL |
| Service | `ConditionalTaxonQueryBuilder` | Condition queries, admin preview |
| Service | `FacetProductQueryBuilder` | Storefront filters, facet counts |
| Service | `SelectAttributeValueResolver` | Select attribute choices matched in PHP, cached for the request |
| Service | `FacetReferenceChecker` (implements `FacetReferenceCheckerInterface`) | Tells whether the attribute, option or taxon of a condition exists (validation and query) |
| Service | `ConditionalTaxonProductAssigner` (implements `ConditionalTaxonSynchronizerInterface`) | Differential materialization of conditional taxons |
| Service | `TaxonIconUploader`, `UploadedImageResizer` | Pictogram storage through the Sylius image uploader; GD downscale of uploaded media |
| Grid | `ShopProductGridListener`, `ShopProductListQueryBuilder` | Route the `sylius_shop_product` grid query through the plugin |
| EventListener / Message / MessageHandler | `ConditionalTaxonSyncListener`, `SynchronizeConditionalTaxon`, `SynchronizeConditionalTaxonHandler` | Trigger the synchronization after a flush |
| EventListener | `TaxonIconRemovalListener` | Removes the pictogram file a taxon no longer uses (taxon deleted, icon replaced) once the change is flushed |
| Validator | `ValidFacetCondition`, `NotUniverseMainTaxon` | Condition consistency; a universe cannot be a product main taxon |
| Form | `TaxonTypeExtension`, `TaxonAppearanceTypeExtension`, `TaxonMediaZonesTypeExtension`, `ChannelTypeExtension`, `FacetConditionType`, `TaxonMediaType`, `TaxonImageTranslationType`, `TaxonFeaturedItemsTranslationType`, two UX Autocomplete types | Admin forms |
| Twig | `AdvancedTaxonExtension` (declarations) and the runtimes `FacetRuntime`, `IconLibrariesRuntime`, `MenuRuntime`, `UniverseRuntime`, `VisibilityRuntime`, built on the first call | Functions used by the plugin templates ([Twig functions](../shop/twig-functions.md)) |
| Controller | `AdminFacetPreviewController` | `POST` preview of a condition set |
| Command | `SyncConditionalTaxonsCommand` | Full resynchronization |
| DependencyInjection | `Configuration`, `CylleneDigitalSyliusAdvancedTaxonExtension` | `icon_libraries` config, Doctrine mapping, migrations, Liip filter sets, the `at` UX Icons set |

Services are defined explicitly in `config/services/*.php`, without autowiring nor autoconfiguration,
as Symfony recommends for reusable bundles. The wiring rules are in
[Code map](../development/code-map.md#wiring).

A file-by-file map is in [Code map](../development/code-map.md).

## Main flows

### Taxon page (product list)

1. The Sylius grid `sylius_shop_product` is converted; `ShopProductGridListener`
   (`sylius.grid.shop_product` event) replaces the repository method
   `createShopListQueryBuilder` by
   `expr:service('cyllene_digital_sylius_advanced_taxon.grid.shop_product_list_query_builder')` →
   `createListQueryBuilder`, and adds the argument
   `advancedFilters: expr:service('request_stack').getCurrentRequest().query.all('advanced')`.
   An application that already replaced the method keeps its own query.
2. `ShopProductListQueryBuilder::createListQueryBuilder()` calls the native
   `ProductRepository::createShopListQueryBuilder()`, with `includeAllDescendants` forced to `true`
   when the taxon has `includeChildrenProducts`.
3. When the taxon has `advancedFiltersEnabled` and `advanced` is not empty,
   `FacetProductQueryBuilder::applyStorefrontFilters()` adds the attribute, option, sub-taxon and
   price constraints to the same query builder.
4. The grid keeps its pagination, sorting, search filter and channel restriction.

### Facets (sidebar)

1. `sidebar/facet_filters.html.twig` resolves the taxon with `advanced_taxon_taxon_from_request()`.
2. `advanced_taxon_available_filters(taxon, locale, includeChildren, app.request.query.all('advanced'))`
   adds the grid search (`criteria[search][value]`) and the `sylius_shop.product_grid.include_all_descendants`
   parameter, then calls `FacetProductQueryBuilder::getAdvancedFiltersData()`.
3. Facet queries start from `createBaseProductsQb()` (enabled products of the current channel,
   assigned to the taxon or its descendants), so the counts match the grid listing. Each facet family
   ignores its own selection when counting alternatives.

### Saving a conditional taxon

1. The entity keeps the flag: `addFacetCondition()` sets `conditional` to `true`,
   `removeFacetCondition()` sets it to `false` when the last condition goes, whatever the entry
   point (admin, API, fixtures, import). The admin form also sets
   `conditional = !facetConditions.isEmpty()` on submit (`TaxonTypeExtension`).
2. `ConditionalTaxonSyncListener::onFlush()` collects the taxons inserted as conditional, the taxons
   whose `conditional` field changed, and the taxons whose conditions were inserted, updated or
   deleted (entity or `facetConditions` collection change).
3. `postFlush()` keeps the id of each collected taxon still managed, that is conditional or just
   stopped being conditional. `SynchronizeConditionalTaxon(taxonId)` is dispatched once the flush is
   over, at the end of the request, of the command or of the handled message.
4. `SynchronizeConditionalTaxonHandler` reloads the taxon and calls
   `ConditionalTaxonSynchronizerInterface::syncTaxon()`; the bus middleware flushes.
5. `ConditionalTaxonProductAssigner` computes the matching product ids with
   `ConditionalTaxonQueryBuilder::buildQuery()` and attaches / detaches `ProductTaxon` links.

Details: [Conditional taxons](../domain/conditional-taxons.md).

### Admin preview of conditions

1. The **Test conditions** button of the conditions tab posts the current rows (JSON) to
   `cyllene_digital_sylius_advanced_taxon_admin_facet_preview`, with the `X-CSRF-Token` header.
2. `AdminFacetPreviewController` checks the token, keeps at most 32 conditions with string fields cut
   to 255 characters, and calls `ConditionalTaxonQueryBuilder::previewConditions()` in the default locale
   (`sylius.translation_locale_provider`), on the same products as the synchronization, disabled
   ones included (rows that are incomplete or whose reference does not exist are skipped).
3. The JSON answer carries the total count and up to 20 products.

### Mega menu

`sylius_shop.base.header.navbar` renders the plugin menu when the current channel has
`hasMegaMenu`. `advanced_taxon_preload_menu()` fetches the first three levels, their translations,
featured children and images in grouped queries.

## Principles

| Principle | Where it shows |
|-----------|----------------|
| Never replace a Sylius model | Traits + interfaces only; relations target `TaxonInterface`, `ProductInterface`, `TaxonImageInterface` (Doctrine `resolve_target_entities`); code tests `instanceof AdvancedTaxonInterface` |
| The taxon listing is the Sylius grid | No parallel product query in templates; facets count what the grid lists |
| Fail closed | A stored condition that is not applicable (incomplete, or whose attribute, option or taxon does not exist) makes the taxon match nothing (`buildQuery()` adds `1 = 0`), negative operators included |
| No `LIKE` on JSON columns | Select attribute values are read once per attribute code and matched in PHP, then filtered by id (`IN` with inlined integers) |
| Twig hooks are imported, not prepended | `config/config.yaml` is imported by the application after the Sylius configuration, so the plugin hooks win over the Sylius core hooks they override |
| Sanitize on write | `setColor()`, `setIcon()`, `setUrl()` go through `AdvancedTaxonValueSanitizer`; the media display mode setters fall back to their default on an unknown value; `advanced_taxon_media_href()` sanitizes media links again on read |
| Show only what is visible | Featured products and children are filtered at render time (`advanced_taxon_visible_products()`, `advanced_taxon_visible_taxons()`): disabled products, products outside the current channel and disabled taxons are left out |
| Stateless services | Services are `final` and mostly `readonly`; the six holding per-request memory (`SelectAttributeValueResolver`, `FacetReferenceChecker`, `FacetRuntime`, `VisibilityRuntime`, `ConditionalTaxonSyncListener`, `TaxonIconRemovalListener`) implement `ResetInterface`, so they are reset between requests (FrankenPHP worker mode, Messenger workers) |

## Entry files

| File | Role |
|------|------|
| `src/CylleneDigitalSyliusAdvancedTaxonPlugin.php` | Bundle class (`SyliusPluginTrait`), path = repository root |
| `src/DependencyInjection/CylleneDigitalSyliusAdvancedTaxonExtension.php` | Loads `config/services.php`, builds the `icon_libraries` parameter, prepends the Doctrine mapping, the migrations, the Liip filter sets and the `at` UX Icons set |
| `config/config.yaml` | Imports `config/twig_hooks/**/*.yaml` (to be imported by the application) |
| `config/routes.yaml` | Imports `config/routes/admin.yaml` (the preview route) under the back-office prefix; imported by the application |
| `config/validation/Product.xml` | `NotUniverseMainTaxon` on `Sylius\Component\Core\Model\Product` |
