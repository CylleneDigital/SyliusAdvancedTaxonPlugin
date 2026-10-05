# Troubleshooting

Symptom, cause, fix. Start with:

```bash
bin/console cache:clear
bin/console sylius:debug:twig-hooks sylius_admin.taxon.update.content.sections.form --all
```

The second command (Sylius 2.3; on 2.1 and 2.2, `bin/console debug:config sylius_twig_hooks`) must
list the `taxon_tab_*` hookables and the Sylius `general`, `translations` and `images` as disabled
(`…sections.side_navigation` lists the tab buttons).

## Installation

### The taxon form and the shop look like plain Sylius

**Cause.** `@CylleneDigitalSyliusAdvancedTaxonPlugin/config/config.yaml` is not imported, or is
imported before the Sylius configuration. That file loads every Twig hook of the plugin; Sylius
hookables defined after it win.

**Fix.** Import it from a file of `config/packages/` sorted after `_sylius.yaml`, for example
`config/packages/cyllene_digital_sylius_advanced_taxon.yaml`. See
[Installation](installation.md#import-the-plugin-configuration).

### `Neither the property "isUniverse" nor one of the methods …` (or `hasMegaMenu`, `showCustomizationInBreadcrumbs`, …)

**Cause.** The Sylius models are not the extended entities: the trait or interface is missing, or the
class is not declared as the `model` of `sylius_taxonomy` / `sylius_channel` / `sylius_core`
resources. With `strict_variables` off (production), the same cause shows as missing plugin blocks
instead of an error: the shop helpers ignore a taxon that is not an `AdvancedTaxonInterface`.

**Fix.** [Installation, step 3](installation.md#3-extend-the-sylius-entities). Check the result:

```bash
bin/console debug:container --parameter=sylius.model.taxon.class
bin/console debug:container --parameter=sylius.model.channel.class
bin/console debug:container --parameter=sylius.model.taxon_image.class
```

### `Unable to generate a URL for the named route "cyllene_digital_sylius_advanced_taxon_admin_facet_preview"`

**Cause.** The plugin routes are not imported. The conditions tab of the taxon form needs that route.

**Fix.** Import `@CylleneDigitalSyliusAdvancedTaxonPlugin/config/routes.yaml`:
[Installation](installation.md#import-the-routes).

### `You have requested a non-existent service "liip_imagine.filter.configuration"`, or `Unknown "imagine_filter" filter`

**Cause.** `LiipImagineBundle` is not enabled. The plugin requires it, and only declares its filter
sets when the bundle is registered.

**Fix.** Register `Liip\ImagineBundle\LiipImagineBundle` in `config/bundles.php` (a Sylius
application already does).

### The plugin migration is not listed

**Cause.** `sylius_core.prepend_doctrine_migrations` is `false`: Sylius plugins then do not register
their migrations.

**Fix.** Register the path yourself:

```yaml
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    migrations_paths:
        'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations': '@CylleneDigitalSyliusAdvancedTaxonPlugin/src/Migrations'
```

### The database only holds the `cyllene_advanced_taxon_*` tables

**Cause.** MariaDB with Doctrine DBAL 4. The Sylius core migrations run only when the platform is
MySQL (`Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration::isMySql()`). With DBAL 4,
`MariaDBPlatform` no longer extends `MySQLPlatform`: every Sylius migration is skipped and recorded
as executed, and only the plugin migration creates its tables.

**Fix.** Declare a **MySQL** server version in the connection URL, and create the database in
`utf8mb4_unicode_ci` (the collation of the plugin tables on MySQL platforms):

```dotenv
DATABASE_URL="mysql://app:app@127.0.0.1:3306/app_%kernel.environment%?charset=utf8mb4&serverVersion=8.0.36"
```

```bash
bin/console dbal:run-sql "ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

On a database where the Sylius migrations were already recorded as executed, re-create it before
migrating again.

## Assets

### Admin tabs unstyled, icon picker or conditions do nothing, shop sliders and filters panel inert

**Cause.** The plugin entrypoints are not imported, or the assets were not rebuilt.

**Fix.** Import both entrypoints and rebuild: [Assets](assets.md#import-the-entrypoints).

### The build fails on a `.scss` file

**Cause.** The Encore configuration compiling your entrypoint does not enable the Sass loader.

**Fix.** Add `.enableSassLoader()` to that configuration. `sass` and `sass-loader` come with
`@sylius-ui/admin` and `@sylius-ui/shop`.

### Icons missing, or slow first renders

**Cause.** The plugin templates only use local icons (`tabler:` from Sylius, `at:` from the plugin).
The taxon icons chosen in the back office with another prefix (your `icon_libraries`) are fetched from
the Iconify API on first render. Sylius sets UX Icons to ignore icons it cannot find: without
network access, they render as nothing, without error.

**Fix.** Store them locally with `bin/console ux:icons:import`. See [Assets](assets.md#icons).

## Shop

### The taxon page ignores `Include products from child taxons` and the advanced filters

**Cause.** The `sylius_shop_product` grid no longer uses `createShopListQueryBuilder`: the
application or another plugin replaced the repository method. The plugin then leaves the grid
untouched. Same result when extra grid arguments shift the position of the `advancedFilters`
argument.

**Fix.** Check the method:

```bash
bin/console debug:config sylius_grid grids.sylius_shop_product.driver
```

It must show `method: createShopListQueryBuilder` and the five native arguments (the plugin changes
them at runtime, not in the configuration).

Keep `createShopListQueryBuilder` with its native arguments, or call `ShopProductListQueryBuilder`
from your own method. See [Configuration](configuration.md#taxon-product-listing-sylius_shop_product-grid).

Also check the taxon toggles: advanced filters only apply when `Enable advanced filters in the shop` is
on and the URL carries `advanced[...]` parameters.

### Every taxon listing includes its sub-taxon products

**Cause.** `sylius_shop.product_grid.include_all_descendants` is `true`. It overrides the
per-taxon toggle, for the listing and the facet counts.

**Fix.** Set it to `false` and enable `Include products from child taxons` on the taxons that need it.

### The sidebar no longer lists sub-categories

**Cause.** The taxon has `Enable advanced filters in the shop` on: the facet filters replace the child
taxon links. The plugin disables the Sylius `taxonomy` hookable of
`sylius_shop.product.index.content.body.sidebar`; its `advanced_taxon_taxonomy` hookable shows the
child links and **Go level up** only on taxons without advanced filters.

**Fix.** Turn the advanced filters off on that taxon, or re-enable the Sylius hookable:
[Theming](theming.md#re-enable-a-sylius-hookable).

### The mega menu does not show

**Cause.** It is off for the current channel, or the application redefines the `menu` hookable of
`sylius_shop.base.header.navbar`.

**Fix.** Enable `Enable mega menu` on the channel (`Look & feel`). Check the hookable (Sylius 2.3):

```bash
bin/console sylius:debug:twig-hooks sylius_shop.base.header.navbar --config
```

### The universe slider is heavy on mobile

**Cause.** The `cyllene_universe_slider_mobile` filter set is missing: the slider falls back to
`sylius_original`.

**Fix.** Do not remove the filter set; redefine it instead:
[Configuration](configuration.md#image-filter-sets-liip-imagine).

## Conditional taxons

### Saving a taxon does not update its products

| Cause | Fix |
|---|---|
| `SynchronizeConditionalTaxon` is routed to a transport no worker consumes | Run `bin/console messenger:consume <transport>`; check `bin/console messenger:failed:show` |
| The conditions were added to the `getFacetConditions()` collection directly, bypassing `addFacetCondition()`, so the taxon is not flagged conditional | Use `addFacetCondition()` / `removeFacetCondition()`, or call `setConditional()` |
| No condition changed (rename, move in the tree) | Expected: nothing is dispatched. Run the command |

See [Configuration](configuration.md#conditional-taxon-synchronization-messenger).

### New or changed products do not join their conditional taxon

**Cause.** Only a change of the conditions triggers a synchronization. Product changes are picked up
by the command, which is not scheduled.

**Fix.** Schedule `cyllene:advanced-taxon:sync-conditional-taxons`:
[Installation, step 6](installation.md#6-keep-conditional-taxons-in-sync).

### A product assigned by hand left a conditional taxon

**Cause.** Expected. The synchronization owns every product link of a conditional taxon: a product
that does not match the conditions is detached. A taxon that stops being conditional (its last
condition removed) has **all** its product links detached.

**Fix.** Use a regular taxon for hand-picked products. Rules:
[Conditional taxons](../domain/conditional-taxons.md).

### A conditional taxon stays empty

**Cause.** One of its stored conditions is incomplete or inconsistent (wrong operator for the type,
missing reference or value), typically written by the API, fixtures or an import, or refers to an
attribute, option or taxon that no longer exists. Such a taxon matches no product, on purpose.

**Fix.** Open the taxon in the admin and save it: the form validates every condition
(`ValidFacetCondition`) and shows the faulty one.

## Admin

### "The icon "…" cannot be found in the icon libraries of the shop."

**Cause.** The glyph is in no local icon set and cannot be fetched from Iconify: a typo, a wrong
prefix, or no network on the server.

**Fix.** Pick the icon in the icon picker (**Choose an icon**), or import it into the application
with `bin/console ux:icons:import <prefix>:<name>`.

### "The pictogram must be a PNG, JPEG, WebP or GIF image of 2 MB at most."

**Cause.** The file is SVG, another format, or larger than 2 MB. The type is detected from the file
content, not the extension. A file larger than PHP's `upload_max_filesize` does not even reach the
plugin.

**Fix.** Upload a PNG, JPEG, WebP or GIF of 2 MB at most, or pick a glyph icon. The pictogram is only
stored once the rest of the form is valid.

### "The universe "…" cannot be the main taxon of a product: a universe page does not list products."

**Cause.** The product's main taxon is flagged `This taxon is a universe`
(`NotUniverseMainTaxon`, `sylius` validation group).

**Fix.** Pick another main taxon, or turn the universe off.

### A field added to the taxon form by my application does not show

**Cause.** The plugin renders the taxon form in tabs. The `General` tab renders the Sylius
`…content.sections.form.general` and `…form.translations` hooks, so a hookable added there shows; the
Sylius `images` section is replaced by the media zones, and a hookable added under `…form.images` is
no longer rendered. A field added by a form extension without any hookable is not rendered either
(`render_rest` is off, as in Sylius).

**Fix.** Add your hookable to `sylius_admin.taxon.{create,update}.content.sections.form.general`, or
add a tab: [Theming](theming.md#taxon-form-create-and-update).
