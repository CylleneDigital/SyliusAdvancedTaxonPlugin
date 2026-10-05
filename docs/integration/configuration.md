# Configuration

What an integrator configures, in the application. What a shop admin tunes per taxon or per channel
is in the [Admin user guide](../admin/user-guide.md).

Nothing is required: without any of the settings below, the plugin runs with its defaults.

| Setting | Where | Default |
|---|---|---|
| Extra icon libraries for the admin picker | `cyllene_digital_sylius_advanced_taxon.icon_libraries` | Tabler only |
| Pictogram and mobile slider image sizes | `liip_imagine.filter_sets` | Declared by the plugin |
| Conditional taxon synchronization transport | `framework.messenger.routing` | Synchronous |
| Children products on every taxon listing | `sylius_shop.product_grid.include_all_descendants` | Sylius default |
| Mega menu | Per channel, back office | Off |

## Bundle configuration

The bundle has a single option.

```yaml
# config/packages/cyllene_digital_sylius_advanced_taxon.yaml
cyllene_digital_sylius_advanced_taxon:
    icon_libraries: {}
```

Check what the application uses:

```bash
bin/console config:dump-reference cyllene_digital_sylius_advanced_taxon
bin/console debug:config cyllene_digital_sylius_advanced_taxon
```

### `icon_libraries`

Icon sets offered by the icon picker of the taxon form (`Advanced customizations` tab), on top of
Tabler. Each entry is keyed by a library name.

| Key | Type | Default | Description |
|---|---|---|---|
| `label` | string | `null` | Title of the library in the picker. Empty: the key with `_` and `-` replaced by spaces, first letter upper-cased (`material_design` → `Material design`). |
| `icon_prefix` | string | none (required) | Symfony UX Icons prefix. The stored icon is `<icon_prefix>:<icon>`. Required and not empty: a library without it fails the configuration. |
| `icons` | list of strings | `[]` | Icon names offered in the picker. Empty names are dropped, duplicates removed, the list sorted. |

```yaml
# config/packages/cyllene_digital_sylius_advanced_taxon.yaml
cyllene_digital_sylius_advanced_taxon:
    icon_libraries:
        phosphor:
            label: 'Phosphor'
            icon_prefix: 'ph'
            icons: ['star', 'heart', 'tree-evergreen']
        mdi:
            label: 'Material Design Icons'
            icon_prefix: 'mdi'
            icons: ['home', 'cart', 'star']
```

Behaviour:

- **Tabler** is always listed first, under the key `tabler`. Its icons are discovered from the
  Tabler SVG files shipped by `SyliusUiBundle` (`Resources/assets/icons/tabler/`), which Sylius
  registers as the `tabler` UX Icons set: they render without network access.
- A library keyed `tabler` in your configuration **replaces** the discovered Tabler list.
- The plugin does not check that `icon_prefix` exists in UX Icons. Icons of a prefix that is not a
  local icon set are fetched from Iconify (Sylius enables it): see [Assets](assets.md#icons).

The plugin builds the `cyllene_digital_sylius_advanced_taxon.icon_libraries` container parameter
from this option: a list of `{key, label, icon_prefix, icons}` entries, Tabler first. It feeds the
`advanced_taxon_icon_libraries()` Twig function used by the picker.

```bash
bin/console debug:container --parameter=cyllene_digital_sylius_advanced_taxon.icon_libraries
```

A taxon icon is stored as `<prefix>:<name>`, or as a bare Tabler name, or as `image:<path>` for an
uploaded pictogram. Any other value is dropped on write.

## Image filter sets (Liip Imagine)

When `LiipImagineBundle` is enabled, the plugin extension prepends two filter sets:

| Filter set | Used for | Filters | Quality |
|---|---|---|---|
| `cyllene_advanced_taxon_icon` | Uploaded pictograms (shop menu, breadcrumbs, taxon page title) | `thumbnail`: `size: [160, 160]`, `mode: inset` | Not set: Liip `default_filter_set_settings` (100 by default) |
| `cyllene_universe_slider_mobile` | Universe hero slider and the Slider mode of the top and bottom zones, below 768px | `thumbnail`: `size: [640, 360]`, `mode: outbound`; `strip` | `82` |

Being prepended, they are merged with your configuration: a key you write wins, a key you omit keeps
the plugin value. Redefine them in any configuration file:

```yaml
# config/packages/liip_imagine.yaml
liip_imagine:
    filter_sets:
        cyllene_universe_slider_mobile:
            quality: 75
            filters:
                thumbnail:
                    size: [750, 500]
                    mode: outbound
```

`quality` sits at the filter set level, not under `filters`.

If `cyllene_universe_slider_mobile` does not exist, the slider uses `sylius_original` on mobile. The
plugin templates also use the Sylius filter sets `sylius_original`, `sylius_medium` and
`sylius_shop_taxon_thumbnail`.

Pictograms are stored by the Sylius image uploader (`sylius.uploader.image`), in the same storage as
the other Sylius images (local, S3 or any Flysystem adapter). The taxon stores `image:<path in the
storage>`.

## Conditional taxon synchronization (Messenger)

Changing the conditions of a taxon (adding, editing or removing one, or turning the taxon
conditional or not) dispatches
`CylleneDigital\SyliusAdvancedTaxonPlugin\Message\SynchronizeConditionalTaxon` at the end of the
request, of the console command or of the message a worker handled, for a taxon flagged conditional
or that just stopped being one. Saving a taxon for
another reason (renaming, moving it in the tree) dispatches nothing.

The `conditional` flag is kept by the taxon entity: `addFacetCondition()` sets it,
`removeFacetCondition()` clears it with the last condition. Conditions written through the API,
fixtures or an import therefore trigger the synchronization like the admin form, which also computes
the flag on submit.

The message carries the taxon id. It is dispatched on `sylius.command_bus`, the Sylius command bus,
whose handler is registered on that bus only.
`SynchronizeConditionalTaxonHandler` attaches the matching products, detaches the others, and
detaches every materialized product of a taxon that stopped being conditional.

The message is not routed by the plugin, so it is handled **synchronously**: the admin request that
saves the taxon waits for the assignments. On a large catalog, route it to an asynchronous
transport, for example the `main` transport Sylius declares:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            CylleneDigital\SyliusAdvancedTaxonPlugin\Message\SynchronizeConditionalTaxon: main
```

```bash
bin/console messenger:consume main
```

A routed message is only processed while a worker consumes that transport. Until then, the taxon is
saved but its products do not change.

Products that become eligible later are picked up by the scheduled command, routed or not (crontab
example: [Installation, step 6](installation.md#6-keep-conditional-taxons-in-sync)). Rules and operators:
[Conditional taxons](../domain/conditional-taxons.md).

## Taxon product listing (`sylius_shop_product` grid)

### How the plugin takes over the grid

The taxon page lists products through the Sylius shop grid `sylius_shop_product`, whose driver calls
`ProductRepository::createShopListQueryBuilder()`. The plugin listens to `sylius.grid.shop_product`
(`ShopProductGridListener`) and, when the grid still uses that method:

- replaces it with `ShopProductListQueryBuilder::createListQueryBuilder()`, through the public
  service `cyllene_digital_sylius_advanced_taxon.grid.shop_product_list_query_builder`;
- appends an `advancedFilters` argument, read from the `advanced` query parameter.

`createListQueryBuilder()` calls the native `createShopListQueryBuilder()` of
`sylius.repository.product`, then:

- includes the products of descendant taxons when the taxon has `Include products from child
  taxons` enabled;
- applies the storefront filters when the taxon has `Enable advanced filters in the shop` enabled
  and the request carries some.

Pagination, sorting, limits, the search filter and the channel restriction stay the native ones.

| Grid repository method in your application | Result |
|---|---|
| `createShopListQueryBuilder` (Sylius default), on any product repository class | Taken over: children products and advanced filters work |
| Any other method (replaced by the application or another plugin) | Left untouched: children products and advanced filters have no effect on the listing |

The grid driver passes the arguments **by position**. The plugin appends `advancedFilters` after the
five native arguments (`channel`, `taxon`, `locale`, `sorting`, `includeAllDescendants`). An
application that keeps `createShopListQueryBuilder` but adds its own arguments to the grid shifts
that position: keep the native argument list, or call `ShopProductListQueryBuilder` from your own
method.

### `sylius_shop.product_grid.include_all_descendants`

Sylius setting, not a plugin one:

```yaml
sylius_shop:
    product_grid:
        include_all_descendants: true
```

When `true`, every taxon listing includes the products of its descendants, whatever the taxon's own
`Include products from child taxons` toggle. The facet counts of the advanced filters
(`FacetRuntime`) read the same parameter, so they always count what the listing shows.

| `include_all_descendants` | Taxon toggle | Listing and facets include descendants |
|---|---|---|
| `false` | off | no |
| `false` | on | yes |
| `true` | any | yes |

## Mega menu (per channel)

The mega menu is enabled per channel, not globally: `Configuration > Channels`, edit a channel,
`Look & feel` section, `Enable mega menu` (`has_mega_menu` column, `MegaMenuChannelInterface`).

When it is off, the shop keeps the Sylius menu structure, with the taxon customizations (color,
icon) of the plugin templates. To remove those too, see
[Theming](theming.md#disable-a-feature).

## Validation

The plugin adds two constraints, both in the `sylius` validation group, which the Sylius admin forms
and API use:

| Constraint | On | Rule |
|---|---|---|
| `NotUniverseMainTaxon` | `Sylius\Component\Core\Model\Product` (`config/validation/Product.xml`) | A universe taxon cannot be the main taxon of a product |
| `ValidFacetCondition` | `FacetCondition` (attribute) | Operator allowed for the condition type; reference required for attribute, option and taxon conditions, existing, and never the taxon itself; value required except for stock and taxon membership; 255 characters at most |

A form or command validating products with other groups only skips these checks.

Uploaded pictograms are checked by the form, not by a constraint: PNG, JPEG, WebP or GIF (detected
from the file content), 2 MB at most. SVG is refused. A new glyph icon is also checked by the form:
one the shop cannot render (missing from its icon sets) is refused.

## Translations

The plugin ships `messages` and `validators` catalogues in English and French, under the
`cyllene_digital_sylius_advanced_taxon` key prefix. Override a message in your
`translations/messages.<locale>.yaml` with the same key.
