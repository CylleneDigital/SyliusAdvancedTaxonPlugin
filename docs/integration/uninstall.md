# Uninstall

Remove the plugin in this order: the database rollback needs the plugin migration class, so it runs
**before** `composer remove`.

## 1. Save what you want to keep

The rollback drops the plugin columns and tables. Note first what you may need afterwards.

Uploaded pictograms are files in the Sylius image storage; only `sylius_taxon.icon` points to them
(`image:<path>`). List them before the column disappears:

```bash
bin/console dbal:run-sql "SELECT code, icon FROM sylius_taxon WHERE icon LIKE 'image:%'"
```

Remove the cached thumbnails of the plugin filter sets while they are still defined:

```bash
bin/console liip:imagine:cache:remove --filter=cyllene_advanced_taxon_icon --filter=cyllene_universe_slider_mobile
```

## 2. Roll back the migration

```bash
bin/console doctrine:migrations:execute 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000' --down
```

The `down()` method:

- drops the plugin columns from `sylius_taxon`, `sylius_channel` and `sylius_taxon_image`;
- drops the `cyllene_advanced_taxon_featured_children`, `cyllene_advanced_taxon_featured_products`,
  `cyllene_advanced_taxon_facet_condition`, `cyllene_advanced_taxon_featured_items_translation` and
  `cyllene_advanced_taxon_image_translation` tables and, on PostgreSQL, their three id sequences;
- removes the migration from the executed versions.

It drops every column of its list that exists, **including a column your application already had
before the plugin** (`up()` skipped it, `down()` does not know). Check your own entities first,
especially `sylius_taxon_image.title`, `url`, `description` and `position`, and
`sylius_taxon.color` and `icon`. The full list is in [Schema](../persistence/schema.md).

### Keeping the schema instead

Skipping the rollback is possible: every column the plugin adds is nullable or has a default, so
Sylius keeps inserting rows without knowing them. The plugin tables stay, unused. The executed
version stays recorded; remove it while the class still exists if you do not want
`doctrine:migrations:status` to report it:

```bash
bin/console doctrine:migrations:version 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000' --delete
```

## 3. What happens to the data

| Data | After the rollback |
|---|---|
| Products assigned by conditional taxons | Stay in `sylius_product_taxon` as ordinary product ↔ taxon links. Nothing synchronizes them any more. The conditions are dropped |
| Products assigned by hand | Unchanged |
| Advanced media | Rows stay in `sylius_taxon_image`, with their zone as `type` (`main`, `top`, `bottom`, `left`, `right`, `featured`, `slider_universe`). Their title, link, description, position and translations are dropped |
| Pictogram files | Stay in the Sylius image storage, no longer referenced (see step 1) |
| Featured children, featured products, universe settings, colors, icons, display toggles | Dropped |
| Channel mega menu toggle | Dropped |

Once the plugin templates are gone, Sylius renders the taxon images again: the admin `Images`
section lists the media with their zone type, and the shop taxon header shows the **first** image of
the taxon, whatever its type. Delete the media you do not want to see, from the admin or by type.

## 4. Remove the code

### Entities

From your `Taxon`, `Channel` and `TaxonImage` entities, remove:

- `AdvancedTaxonInterface` / `AdvancedTaxonTrait` and the `$this->initializeAdvancedTaxon()` call;
- `MegaMenuChannelInterface` / `MegaMenuChannelTrait`;
- `AdvancedTaxonImageInterface` / `AdvancedTaxonImageTrait` and the
  `$this->initializeAdvancedTaxonImage()` call.

If an entity only existed for the plugin, delete it and its `model` declaration under
`sylius_taxonomy`, `sylius_channel` or `sylius_core` resources (see
[Installation, step 3](installation.md#3-extend-the-sylius-entities)).

### Assets

Remove the two imports from `assets/admin/entrypoint.js` and `assets/shop/entrypoint.js`, then
rebuild:

```bash
yarn build
```

### Configuration and templates

| Remove | If present |
|---|---|
| `config/packages/cyllene_digital_sylius_advanced_taxon.yaml` | Imports the plugin config, holds `icon_libraries` |
| `config/routes/cyllene_digital_sylius_advanced_taxon.yaml` | Imports the admin route |
| `SynchronizeConditionalTaxon` routing | `config/packages/messenger.yaml` |
| `cyllene_advanced_taxon_icon` / `cyllene_universe_slider_mobile` redefinitions | `liip_imagine` configuration |
| Hook overrides pointing to `@CylleneDigitalSyliusAdvancedTaxonPlugin/…` or to plugin hookable names | `sylius_twig_hooks` configuration |
| `templates/bundles/CylleneDigitalSyliusAdvancedTaxonPlugin/` | Template overrides |
| Theme templates including `taxon_label.html.twig` or calling `advanced_taxon_*()` | Your templates |
| The `cyllene:advanced-taxon:sync-conditional-taxons` schedule | Crontab, scheduler |

With Flex, `composer remove` (step 5) unregisters the bundle and removes the files the recipe
copied: check that they are gone.

## 5. Remove the package

```bash
composer remove cyllene-digital/sylius-advanced-taxon-plugin
```

Without Flex, also remove the line from `config/bundles.php`:

```php
CylleneDigital\SyliusAdvancedTaxonPlugin\CylleneDigitalSyliusAdvancedTaxonPlugin::class => ['all' => true],
```

Then:

```bash
bin/console cache:clear
bin/console doctrine:schema:validate
```
