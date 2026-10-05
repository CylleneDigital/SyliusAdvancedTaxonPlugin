# Public contract and versioning

This plugin follows [semantic versioning](https://semver.org). The elements below must **not** be
changed in a backward incompatible way without a **major** version bump. Everything else is internal
and may change in a minor version (see [What is internal](#what-is-internal)). Every break is written
in [UPGRADE.md](../../UPGRADE.md) before it is released.

## Entity traits and interfaces

Namespace `CylleneDigital\SyliusAdvancedTaxonPlugin\Entity`. The application entities use them
([Installation](../integration/installation.md)).

| Interface | Trait | Extends | Initializer to call from the constructor |
|-----------|-------|---------|------------------------------------------|
| `AdvancedTaxonInterface` | `AdvancedTaxonTrait` | Sylius `Core\Model\TaxonInterface` | `initializeAdvancedTaxon()` (after `parent::__construct()`) |
| `MegaMenuChannelInterface` | `MegaMenuChannelTrait` | Sylius `Core\Model\ChannelInterface` | none |
| `AdvancedTaxonImageInterface` | `AdvancedTaxonImageTrait` | Sylius `Core\Model\TaxonImageInterface`, `Resource\Model\TranslatableInterface` | `initializeAdvancedTaxonImage()` |

The initializers are `protected` trait methods. `initializeAdvancedTaxon()` creates the
`featuredChildren`, `featuredProducts`, `facetConditions` and `featuredItemsTranslations`
collections; `initializeAdvancedTaxonImage()` creates the `translations` collection. Each trait
carries `@phpstan-require-implements` on its interface.

### `AdvancedTaxonInterface`

Constants:

| Constant | Value |
|----------|-------|
| `FEATURED_PRODUCTS_POSITION_BEFORE_FILTERS` | `before_filters` |
| `FEATURED_PRODUCTS_POSITION_AFTER_FILTERS` | `after_filters` |
| `MEDIA_DISPLAY_MODE_STACKED` | `stacked` |
| `MEDIA_DISPLAY_MODE_RANDOM` | `random` |
| `MEDIA_DISPLAY_MODE_SLIDER` | `slider` (top and bottom zones) |
| `MEDIA_RIGHT_PRODUCTS_POSITION_START` | `products_start` |
| `MEDIA_RIGHT_PRODUCTS_POSITION_END` | `products_end` |
| `MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE` | `products_random_middle` |
| `ICON_IMAGE_PREFIX` | `image:` |

Methods (getter / setter pairs unless noted):

| Group | Methods |
|-------|---------|
| Visual | `getColor()` / `setColor(?string)`, `getIcon()` / `setIcon(?string)`, `getIconType()` / `setIconType(?string)` |
| Conditional | `isConditional()` / `setConditional(bool)`, `getFacetConditions()`, `addFacetCondition(FacetCondition)`, `removeFacetCondition(FacetCondition)` |
| Featured | `getFeaturedChildren()`, `addFeaturedChild(self)`, `removeFeaturedChild(self)`, `getFeaturedProducts()`, `addFeaturedProduct(ProductInterface)`, `removeFeaturedProduct(ProductInterface)`, `isSlider()` / `setIsSlider(bool)`, `isFeaturedProductsActive()` / `setIsFeaturedProductsActive(bool)`, `getFeaturedProductsPosition()` / `setFeaturedProductsPosition(string)` |
| Media display | `getMediaDisplayModeTop()`, `getMediaDisplayModeBottom()`, `getMediaDisplayModeLeft()`, `getMediaDisplayModeProduct()`, `getMediaDisplayModeFeatured()` and their `set…(string)` |
| Listing | `isIncludeChildrenProducts()` / `setIncludeChildrenProducts(bool)`, `isAdvancedFiltersEnabled()` / `setAdvancedFiltersEnabled(bool)` |
| Universe | `isUniverse()` / `setIsUniverse(bool)`, `isUniverseBackgroundUseTaxonColor()` / `setUniverseBackgroundUseTaxonColor(bool)`, `isUniverseTitleBackgroundEnabled()` / `setUniverseTitleBackgroundEnabled(bool)` |
| Customization visibility | `isShowCustomizationInMenu()`, `isShowCustomizationOnTaxonPage()`, `isShowCustomizationInBreadcrumbs()` and their `set…(bool)` |
| Featured items translations | `getFeaturedItemsTranslations()`, `addFeaturedItemsTranslation()`, `removeFeaturedItemsTranslation()`, `getFeaturedItemsTranslation(?string $locale = null)`, `getOrCreateFeaturedItemsTranslation(string $locale)` |
| Translated texts (`?string $locale = null`) | `getFeaturedProductsTitle()`, `getFeaturedProductsDescription()`, `getFeaturedChildrenTitle()`, `getFeaturedChildrenDescription()`, `getUniversePageTitle()`, `getUniversePageDescription()`, `getUniverseFeaturedProductsTitle()`, `getUniverseFeaturedProductsDescription()` |

`setFeaturedProductsPosition()` and `setMediaDisplayModeProduct()` fall back to their default
(`before_filters`, `products_end`) on an unknown value; `setMediaDisplayModeTop()`, `…Bottom()`,
`…Left()` and `…Featured()` fall back to `stacked`; `slider` is only kept by
`setMediaDisplayModeTop()` and `…Bottom()`.

`addFacetCondition()` sets `conditional` to `true`; `removeFacetCondition()` sets it to `false` when
the last condition is removed. Code writing conditions through these methods (API, fixtures, import)
needs no `setConditional()` call.

### `MegaMenuChannelInterface`

`hasMegaMenu(): bool`, `setHasMegaMenu(bool): void`.

### `AdvancedTaxonImageInterface`

`getTranslation(?string $locale = null): TaxonImageTranslation`, `getTitle()` / `setTitle(?string)`,
`getTitleForLocale(?string)`, `getUrl()` / `setUrl(?string)`, `getDescription()` /
`setDescription(?string)`, `getDescriptionForLocale(?string)`, `getPosition()` / `setPosition(int)`,
`isShowCardText()` / `setShowCardText(bool)`.

### Plugin entities

`FacetCondition`, `TaxonFeaturedItemsTranslation` and `TaxonImageTranslation` are part of the
contract through the interfaces above (their getters and setters). The `FacetCondition` constants are
stable: `TYPE_*`, `TYPES_WITH_REFERENCE`, `TYPES_WITHOUT_VALUE`, `NEGATIVE_OPERATORS`, `MAX_LENGTH`,
`OPERATORS_BY_TYPE`, as are its static helpers `requiresReference()`, `requiresValue()`,
`isNegativeOperator()`, `negatedOperator()`. Values: [Conditional taxons](../domain/conditional-taxons.md).

Removing or renaming a method or a constant, or changing a signature, is a **major** change: the
application entities implement these interfaces and may override trait methods.

## Messenger message

| Element | Value |
|---------|-------|
| Class | `CylleneDigital\SyliusAdvancedTaxonPlugin\Message\SynchronizeConditionalTaxon` (`final readonly`) |
| Payload | `public int $taxonId` |
| Bus | `sylius.command_bus` |
| Routing | none by the plugin: handled synchronously unless the application routes it to a transport |

The class name and its payload are stable: an application routes it by class name, and messages
already queued in a transport must keep deserializing after a minor upgrade.

## Console command

```bash
bin/console cyllene:advanced-taxon:sync-conditional-taxons
```

No argument, no option. Exit code `0`. The name is stable (crontabs, schedulers). Behaviour:
[Conditional taxons](../domain/conditional-taxons.md#console-command).

## Admin route

| Element | Value |
|---------|-------|
| Name | `cyllene_digital_sylius_advanced_taxon_admin_facet_preview` |
| Method | `POST` |
| Path | `/%sylius_admin.path_name%/advanced-taxon/facet-conditions/preview` (`/admin/…` by default) |
| Access | `ROLE_ADMINISTRATION_ACCESS` |
| CSRF | header `X-CSRF-Token`, token id `cyllene_digital_sylius_advanced_taxon_facet_preview` |
| Limits | 32 conditions per request (the rest is ignored), 20 products returned, string fields cut to 255 characters |

Request body (JSON):

```json
{
    "conditions": [
        {"conditionType": "name", "operator": "contains", "referenceCode": null, "value": "Watch"}
    ]
}
```

Non-array entries of `conditions` and non-string fields are dropped. The preview reads names and
descriptions in the default locale (`sylius.translation_locale_provider`) and counts disabled
products, as the synchronization does; there is no `locale` key.

Responses:

| Status | Body |
|--------|------|
| `200` | `{"count": <int>, "products": [{"id": <int>, "code": <string>, "name": <string\|null>, "url": <string>}], "limit": 20}`; `url` is the `sylius_admin_product_show` path |
| `403` | `{"error": "invalid_csrf_token"}` |

The route name, method, header, payload keys and response keys are stable. The application imports
the route file `@CylleneDigitalSyliusAdvancedTaxonPlugin/config/routes.yaml`
([Installation](../integration/installation.md)).

## Bundle configuration

Root key `cyllene_digital_sylius_advanced_taxon`:

| Key | Default | Content |
|-----|---------|---------|
| `icon_libraries` | `[]` | `{name: {label: ?string = null, icon_prefix: string (required, not empty), icons: list<string> = []}}` |

Reference: [Bundle configuration](../integration/configuration.md). The built `tabler` library is
always first; a configured library with the key `tabler` replaces it.

### Reserved icon prefix

The plugin registers the UX Icons set `at` (`assets/icons/`), used by its templates. Do not declare a
set with that prefix in your application.

### Container parameter

`cyllene_digital_sylius_advanced_taxon.icon_libraries`: list of
`{key, label, icon_prefix, icons}` (icons sorted, deduplicated, empty values removed; `label`
defaults to the key with `_` / `-` replaced by spaces, first letter uppercased).

## Liip Imagine filter sets

Prepended when LiipImagineBundle is registered, so an application definition of the same name wins:

| Filter set | Definition | Used for |
|------------|-----------|----------|
| `cyllene_advanced_taxon_icon` | `thumbnail` 160 × 160, `inset` | Uploaded pictograms |
| `cyllene_universe_slider_mobile` | `thumbnail` 640 × 360, `outbound`; `strip`; `quality: 82` | Universe hero slider and the Slider mode of the top and bottom zones, below 768px |

The names are stable.

## Twig functions

Names, arguments and kind of returned value of the functions listed in
[Twig functions](../shop/twig-functions.md) are stable. The PHP extension classes behind them are
internal.

## Twig hooks

The hook names and hookable names the plugin declares (`config/twig_hooks/admin.yaml`,
`config/twig_hooks/shop.yaml`) are stable: projects move, disable or extend them. The list is in
[Theming](../integration/theming.md). The templates they render are not stable: override them at
your own risk.

## Storefront query parameters

Read on taxon pages (`sylius_shop_product_index`):

| Parameter | Structure | Notes |
|-----------|-----------|-------|
| `advanced[attributes][<attributeCode>][]` | list of values | Text attributes: the text value; select attributes: the choice key. Several values of one code are OR-ed, codes are AND-ed |
| `advanced[options][<optionCode>][]` | list of values | The option value **translated in the current locale**. OR within a code, AND between codes, all on one enabled variant |
| `advanced[taxons][]` | list of taxon ids (integers > 0) | Products assigned to any of them |
| `advanced[price][min]`, `advanced[price][max]` | decimal, in currency units | Multiplied by 100 and rounded, negative values become `0`, swapped when `min > max`; same variant as the option filters |
| `advanced[search]` | string | Ignored by the grid query (the grid applies `criteria[search][value]`) |
| `view` | `grid` (default) or `list` | Display mode of the product list |

Values are trimmed; empty values, non-string values and non-string codes are ignored. A request
keeps at most 10 attribute and option codes all together (`FacetProductQueryBuilder::MAX_FILTER_CODES`),
50 values per code and 50 sub-taxons (`MAX_FILTER_VALUES`); the rest is ignored. Filters apply
only when the taxon has `advancedFiltersEnabled`. The facet form also carries `criteria[search][value]`,
`limit` and `sorting[...]` from the current request.

## Database

Tables `cyllene_advanced_taxon_facet_condition`, `cyllene_advanced_taxon_featured_items_translation`,
`cyllene_advanced_taxon_image_translation`, `cyllene_advanced_taxon_featured_children`,
`cyllene_advanced_taxon_featured_products`, their id sequences on PostgreSQL, and the columns added
to `sylius_taxon`, `sylius_channel`, `sylius_taxon_image`: [Schema](../persistence/schema.md). The stored values
(condition types and operators, display modes, `image:` icon prefix) are stable. A schema change
ships with a migration and, when it affects existing data, an [UPGRADE.md](../../UPGRADE.md) entry.

## Validation

| Constraint | Target | Group | Message keys (`validators` domain) |
|------------|--------|-------|-------------------------------------|
| `ValidFacetCondition` | class `FacetCondition` (attribute) | `sylius` | `cyllene_digital_sylius_advanced_taxon.facet_condition.invalid_type`, `.invalid_operator`, `.reference_required`, `.reference_not_found` (`%code%`), `.self_reference`, `.value_required`, `.too_long` (`%limit%`) |
| `NotUniverseMainTaxon` | class `Sylius\Component\Core\Model\Product` (`config/validation/Product.xml`) | `sylius` | `cyllene_digital_sylius_advanced_taxon.product.main_taxon_universe` (`%taxon%`), at path `mainTaxon` |

The constraint classes, their groups and message keys are stable. The pictogram upload error
(`cyllene_digital_sylius_advanced_taxon.icon.invalid_file`) and the unknown icon error (`.icon.not_found`,
`%icon%`) are form errors, not constraints.

## Translations

| Domain | Files | Key prefix |
|--------|-------|------------|
| `messages` | `translations/messages.{en,fr}.yaml` | `cyllene_digital_sylius_advanced_taxon.` |
| `validators` | `translations/validators.{en,fr}.yaml` | `cyllene_digital_sylius_advanced_taxon.` |

Locales shipped: `en`, `fr`. Removing or renaming a key is a major change (projects override them);
adding one is minor.

## Public service id

| Service id | Target | Why public |
|------------|--------|------------|
| `cyllene_digital_sylius_advanced_taxon.grid.shop_product_list_query_builder` | `ShopProductListQueryBuilder` | Called by the Sylius grid through an expression (`ShopProductGridListener::QUERY_BUILDER_SERVICE`) |

Its method `createListQueryBuilder(ChannelInterface $channel, TaxonInterface $taxon, string $locale, array $sorting = [], bool $includeAllDescendants = false, array $advancedFilters = []): QueryBuilder`
is stable, so that a project grid configuration can call it.

## What is internal

Everything not listed above, in particular:

- every service class (all `final`) and interface, and their aliases:
  `ConditionalTaxonQueryBuilder`, `FacetProductQueryBuilder`, `SelectAttributeValueResolver`,
  `DqlExpressions`, `FacetReferenceChecker` and `FacetReferenceCheckerInterface`,
  `ConditionalTaxonProductAssigner`, `ConditionalTaxonSynchronizerInterface`, `TaxonIconUploader`, `UploadedImageResizer`,
  `ShopProductGridListener`, `ConditionalTaxonSyncListener`, `TaxonIconRemovalListener`,
  `SynchronizeConditionalTaxonHandler`,
  `AdminFacetPreviewController`, `SyncConditionalTaxonsCommand`;
- `AdvancedTaxonValueSanitizer` (its rules are documented in [Schema](../persistence/schema.md#write-time-sanitization),
  the class is not API);
- form types and extensions, their field names, the UX Autocomplete aliases
  (`cyllene_advanced_taxon_child_taxon`, `cyllene_advanced_taxon_attached_product`);
- Twig extension classes, the plugin templates, Stimulus controllers and CSS classes;
- `Configuration` and the DI extension, service ids other than the one above;
- the migration class.

Decorating or replacing an internal service works, but is not covered by semver.

## Support policy

| Line | What is covered |
|------|-----------------|
| Sylius | 2.1, 2.2 and 2.3 (CI) |
| Symfony | 7.4 and 8.x (8.x with PHP 8.4+ and Sylius 2.3 in CI) |
| PHP | 8.3+ (CI: 8.3, 8.4, 8.5) |
| Databases (CI) | MySQL 8.0 and 8.4, MariaDB 10.11 and 11.4, PostgreSQL 15, 16 and 17 |

Support for a Sylius, Symfony or PHP version ends when it leaves the CI matrix. Report security
flaws privately: [SECURITY.md](../../SECURITY.md).
