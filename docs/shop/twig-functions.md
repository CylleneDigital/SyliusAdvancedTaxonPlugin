# Twig functions

The plugin registers 18 Twig functions, declared by `AdvancedTaxonExtension` and served by five
runtimes in `src/Twig/Runtime/`: a runtime and its dependencies are only built when a template calls
one of its functions. Their names, arguments and kind of returned value are **public API**
([Public contract](../architecture/public-contract.md#twig-functions)); the PHP classes are internal.

| Function | Runtime | Used by |
|----------|-----------|---------|
| [`advanced_taxon_taxon_from_request()`](#advanced_taxon_taxon_from_request) | `FacetRuntime` | Shop |
| [`advanced_taxon_available_filters()`](#advanced_taxon_available_filters) | `FacetRuntime` | Shop |
| [`advanced_taxon_facet_choices()`](#advanced_taxon_facet_choices) | `FacetRuntime` | Admin |
| [`advanced_taxon_icon_libraries()`](#advanced_taxon_icon_libraries) | `IconLibrariesRuntime` | Admin |
| [`advanced_taxon_ordered_children()`](#advanced_taxon_ordered_children) | `MenuRuntime` | Shop |
| [`advanced_taxon_preload_menu()`](#advanced_taxon_preload_menu) | `MenuRuntime` | Shop |
| [`advanced_taxon_preload_universe()`](#advanced_taxon_preload_universe) | `MenuRuntime` | Shop |
| [`advanced_taxon_universe_data()`](#advanced_taxon_universe_data) | `UniverseRuntime` | Shop |
| [`advanced_taxon_universe_slider_media()`](#advanced_taxon_universe_slider_media) | `UniverseRuntime` | Shop |
| [`advanced_taxon_universe_bottom_media()`](#advanced_taxon_universe_bottom_media) | `UniverseRuntime` | Shop |
| [`advanced_taxon_universe_child_card_data()`](#advanced_taxon_universe_child_card_data) | `UniverseRuntime` | Shop |
| [`advanced_taxon_has_featured_children_products()`](#advanced_taxon_has_featured_children_products) | `UniverseRuntime` | Shop |
| [`advanced_taxon_media_url()`](#advanced_taxon_media_url) | `UniverseRuntime` | Shop |
| [`advanced_taxon_media_mobile_url()`](#advanced_taxon_media_mobile_url) | `UniverseRuntime` | Shop |
| [`advanced_taxon_media_href()`](#advanced_taxon_media_href) | `UniverseRuntime` | Shop |
| [`advanced_taxon_featured_media_url()`](#advanced_taxon_featured_media_url) | `UniverseRuntime` | Shop |
| [`advanced_taxon_visible_products()`](#advanced_taxon_visible_products) | `VisibilityRuntime` | Shop |
| [`advanced_taxon_visible_taxons()`](#advanced_taxon_visible_taxons) | `VisibilityRuntime` | Shop |

`vendor/bin/console debug:twig` lists them with their default arguments.

## Taxon and facets (`FacetRuntime`)

### `advanced_taxon_taxon_from_request`

```
advanced_taxon_taxon_from_request(): ?AdvancedTaxonInterface
```

The taxon of the current page, found by the `slug` request attribute and the current locale
(`TaxonRepository::findOneBySlug()`). `null` when the request has no `slug` attribute, or when the
taxon does not implement `AdvancedTaxonInterface`.

**Memoized** per `locale/slug` for the request (the page templates call it many times); the memory is
cleared by `reset()` (`ResetInterface`).

```twig
{% set taxon = hookable_metadata.context.taxon|default(advanced_taxon_taxon_from_request()) %}
```

### `advanced_taxon_available_filters`

```
advanced_taxon_available_filters(AdvancedTaxonInterface taxon, string localeCode, bool includeChildren = false, array filters = []): array
```

Facet data of a taxon page for the advanced filters sidebar. `filters` is the `advanced` query
parameter ([structure](../architecture/public-contract.md#storefront-query-parameters)). The
function adds the grid search (`criteria[search][value]` of the current request) and widens to the
descendants when `includeChildren` is true **or** the `sylius_shop.product_grid.include_all_descendants`
parameter is true, so the counts match the products the grid lists. The search is applied as the
Sylius grid `search` filter does: `LIKE` on the product name in `localeCode` (case-sensitive on
PostgreSQL).

Returns:

```
{
  selected: {attributes: {code: [values]}, options: {code: [values]}, taxons: [ids],
             price: {min: ?int, max: ?int}, search: string},          # normalized filters, prices in cents
  facets: {
    attributes: [{code, label, values: [{value, label, count, active}]}],
    options:    [{code, label, values: [{value, label, count, active}]}],
    taxons:     [{id, code, label, count, active}],                    # descendants of the taxon
    price:      {min: ?float, max: ?float, selectedMin: ?float, selectedMax: ?float}   # currency units
  }
}
```

Counted products: enabled, in the current channel, assigned to the taxon (or a descendant). Each
family ignores its own selection, so the alternatives keep a meaningful count. Groups and values are
sorted by label (natural, case-insensitive). Select attribute values carry their choice key as
`value` and the choice label of `localeCode` as `label` (else the first label, else the key).

**Not memoized**: every call runs the facet queries (several per family). The select attribute
values read for matching are cached for the request inside `SelectAttributeValueResolver`.

```twig
{% set activeAdvancedFilters = app.request.query.all('advanced') %}
{% set filtersData = advanced_taxon_available_filters(taxon, app.request.locale, includeChildren, activeAdvancedFilters) %}
{% set selected = filtersData.selected %}
{% set facets = filtersData.facets %}
```

### `advanced_taxon_facet_choices`

```
advanced_taxon_facet_choices(): array{attribute: array<string, string>, option: array<string, string>, taxon_membership: array<string, string>}
```

**Admin.** References a condition can target, by condition type, for the conditions editor: product
attributes (only those stored as `text` or `json`, that is text and select attributes), product
options and taxons, as `label => code`. Labels are the names in the current
locale (the code when missing); a label shared by several references becomes `Label (code)`. Sorted
by label (natural, case-insensitive). Three scalar queries per call, not memoized.

```twig
data-at-admin-taxon-facet-conditions-choices-value="{{ advanced_taxon_facet_choices()|json_encode }}"
```

## Icons (`IconLibrariesRuntime`)

### `advanced_taxon_icon_libraries`

```
advanced_taxon_icon_libraries(): list<array{key: string, label: string, icon_prefix: string, icons: list<string>}>
```

**Admin.** The icon libraries of the icon picker: the content of the
`cyllene_digital_sylius_advanced_taxon.icon_libraries` container parameter, built at container
compile time (Tabler icons discovered from the Sylius UI bundle, then the `icon_libraries`
configuration). No query. See [Bundle configuration](../integration/configuration.md).

```twig
{% set iconLibraries = advanced_taxon_icon_libraries() %}
```

## Menu and children (`MenuRuntime`)

### `advanced_taxon_preload_menu`

```
advanced_taxon_preload_menu(iterable<TaxonInterface> taxons): void
```

Loads in grouped queries what the menu reads on the subtree it renders: the taxons of the same
roots from the first menu level down to 3 levels, their children with translations, then their
`featuredChildren` and `images` collections (one fetch-join query each). Nothing is returned: the
collections are initialized on the managed entities. Taxons that are not `AdvancedTaxonInterface`,
or not persisted, are ignored. Call it once, before walking the menu.

```twig
{% do advanced_taxon_preload_menu(taxons|default([])) %}
```

### `advanced_taxon_preload_universe`

```
advanced_taxon_preload_universe(TaxonInterface taxon): void
```

Loads in grouped queries what a universe page reads: the children of the taxon with their
translations, the media and featured items texts of the taxon and its children, the featured
products of the children, and the grandchildren with their translations (five queries, whatever the
number of children). Nothing is returned. A taxon that is not persisted is ignored.

```twig
{% do advanced_taxon_preload_universe(taxon) %}
```

### `advanced_taxon_ordered_children`

```
advanced_taxon_ordered_children(TaxonInterface taxon): list<TaxonInterface>
```

The enabled children of a taxon, featured children first (in their tree order), then the
other enabled children; a featured taxon that is not an enabled child is left out, duplicates are
removed. Works with any `TaxonInterface` (no featured ordering when the taxon is not an
`AdvancedTaxonInterface`). No memoization; reads the loaded collections.

```twig
{% set level2Taxons = hasChildren ? advanced_taxon_ordered_children(taxon) : [] %}
{% set l3Taxons = advanced_taxon_ordered_children(l2)|slice(0, 5) %}
```

## Universe and media (`UniverseRuntime`)

The media functions read the Sylius taxon images of one zone (`slider_universe`, `bottom` or
`featured`) with a non-empty `path`, sorted by `position` ascending.

### `advanced_taxon_universe_data`

```
advanced_taxon_universe_data(TaxonInterface taxon): array
```

| Key | Value |
|-----|-------|
| `useTaxonColorBackground` | taxon has a color **and** `universeBackgroundUseTaxonColor` |
| `titleBackgroundEnabled` | taxon has a color **and** `universeTitleBackgroundEnabled` |
| `universeButtonColor` | taxon color, else `#0d6efd` |
| `universeAccentColor` | taxon color, else `#ffffff` |
| `universePageTitle` | universe page title of the current locale, else the taxon name |
| `universePageDescription`, `universeFeaturedTitle`, `universeFeaturedDescription` | translated texts or `null` |

Texts come from `TaxonFeaturedItemsTranslation` (current locale, then fallback locale).

```twig
{% set universeData = advanced_taxon_universe_data(taxon) %}
```

### `advanced_taxon_universe_slider_media`

```
advanced_taxon_universe_slider_media(TaxonInterface taxon): list<AdvancedTaxonImageInterface>
```

Media of the `slider_universe` zone. Empty when the taxon is not an `AdvancedTaxonInterface`.

### `advanced_taxon_universe_bottom_media`

```
advanced_taxon_universe_bottom_media(TaxonInterface taxon): list<AdvancedTaxonImageInterface>
```

Media of the `bottom` zone.

```twig
{% set sliderUniverseMedia = advanced_taxon_universe_slider_media(taxon) %}
{% set bottomMedia = advanced_taxon_universe_bottom_media(taxon) %}
```

### `advanced_taxon_universe_child_card_data`

```
advanced_taxon_universe_child_card_data(TaxonInterface taxon): array{mediaUrl: ?string, hasChildColor: bool, childChildren: list<TaxonInterface>}
```

Data of a child card on a universe page: the featured media URL of the child
([`advanced_taxon_featured_media_url()`](#advanced_taxon_featured_media_url)), whether it has a
color, and its enabled children. `{mediaUrl: null, hasChildColor: false, childChildren: []}` for a
taxon that is not an `AdvancedTaxonInterface`.

```twig
{% set childCard = advanced_taxon_universe_child_card_data(child) %}
```

### `advanced_taxon_has_featured_children_products`

```
advanced_taxon_has_featured_children_products(array children): bool
```

Whether at least one of the given taxons has **Enable featured products display** on
(`isFeaturedProductsActive()`) and at least one featured product visible in the shop
([`advanced_taxon_visible_products()`](#advanced_taxon_visible_products)).

```twig
{% set hasFeaturedChildProducts = advanced_taxon_has_featured_children_products(children) %}
```

### `advanced_taxon_media_url`

```
advanced_taxon_media_url(?string path, string filterSet = 'sylius_original'): ?string
```

Browser path of an image of the Sylius image storage through Liip Imagine
(`CacheManager::getBrowserPath()`, absolute path, leading `/` of `path` removed). `null` for an
empty path. Works with any Flysystem storage Liip is configured for.

```twig
{% set mediaUrl = advanced_taxon_media_url(media.path) %}
```

### `advanced_taxon_media_mobile_url`

```
advanced_taxon_media_mobile_url(?string path, string filterSet = 'cyllene_universe_slider_mobile'): ?string
```

Same as `advanced_taxon_media_url()`, with `sylius_original` when `filterSet` is not a configured
filter set.

```twig
{% set desktopImage = advanced_taxon_media_url(media.path) %}
{% set mobileImage = advanced_taxon_media_mobile_url(media.path) %}
```

### `advanced_taxon_media_href`

```
advanced_taxon_media_href(?string url): ?string
```

The destination URL of a media, only when it is safe in an `href`: `http(s)://` URLs and same-origin
paths starting with a single `/` (no `//`, no `/\`, no backslash). Anything else returns `null`.
Same rule as on write ([Schema](../persistence/schema.md#write-time-sanitization)), applied again on
read.

Used by the product card media (grid and list), the universe hero slides and the Slider mode of the
top and bottom zones.

```twig
{% set mediaHref = advanced_taxon_media_href(media.url) %}
```

### `advanced_taxon_featured_media_url`

```
advanced_taxon_featured_media_url(TaxonInterface taxon): ?string
```

URL (`sylius_original`) of the spotlight media of a taxon, from its `featured` zone: the lowest
position in `stacked` mode, a random one in `random` mode (`mediaDisplayModeFeatured`). In `random`
mode **every call picks again**: call it once per render and keep the result. `null` without
`featured` media.

```twig
{% set ftMediaUrl = advanced_taxon_featured_media_url(ft)|default('') %}
```

## Visibility (`VisibilityRuntime`)

Featured products and featured children are stored as picked in the back office. These functions
leave out what must not reach the storefront any more. No memoization.

### `advanced_taxon_visible_products`

```
advanced_taxon_visible_products(iterable products): list<ProductInterface>
```

The enabled products of the list that are available in the current channel (channel context). When
no channel can be resolved, only the enabled check applies. Non-product entries are dropped. The
channels of the products not loaded yet are read in one query for the whole list.

```twig
{% set featuredProducts = taxon is not null ? advanced_taxon_visible_products(taxon.featuredProducts) : [] %}
```

### `advanced_taxon_visible_taxons`

```
advanced_taxon_visible_taxons(iterable taxons): list<TaxonInterface>
```

The enabled taxons of the list. Non-taxon entries are dropped.

```twig
{% set childTaxons = taxon is not null ? advanced_taxon_visible_taxons(taxon.featuredChildren) : [] %}
```

## Related

- [Storefront](storefront.md): what the shop templates render with these functions.
- [Theming](../integration/theming.md): hooks and template overrides.
