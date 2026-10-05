# Installation

## Quickstart (10 lines)

```bash
composer require cyllene-digital/sylius-advanced-taxon-plugin
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
```

With Symfony Flex and the plugin recipe accepted (`extra.symfony.allow-contrib` enabled), `composer
require` already enables the bundle, imports the plugin configuration and routes, and prints the
remaining steps. The recipe itself lives in [`recipe/`](../recipe).

In Sylius admin, go to `Catalog > Taxons` and open a taxon to configure icons,
featured items, advanced medias, and conditional mass assignment.

Conditional taxon synchronization is performed by
`cyllene:advanced-taxon:sync-conditional-taxons`. Schedule it in the background (see
[section 4](#4-schedule-the-conditional-taxon-synchronization)).

## Requirements

- PHP `^8.3` (PHP `^8.4` when running Symfony `8.x`)
- Sylius `^2.2` or `^2.3`
- Symfony `^7.4` or `^8.0`
- A Sylius application with asset building enabled
- A database user allowed to run Doctrine migrations
- A way to run the synchronization command periodically (for example crontab)

## 1. Install the package

```bash
composer require cyllene-digital/sylius-advanced-taxon-plugin
```

If your application does not use Symfony Flex recipes for plugin registration, enable the bundle manually in `config/bundles.php`:

```php
<?php

return [
    // ...
    CylleneDigital\SyliusAdvancedTaxonPlugin\CylleneDigitalSyliusAdvancedTaxonPlugin::class => ['all' => true],
];
```

## 2. Import the plugin routes

Import the plugin routes in your application if they are not registered automatically.

```yaml
# config/routes/cyllene_digital_sylius_advanced_taxon.yaml
cyllene_digital_sylius_advanced_taxon_admin:
    resource: '@CylleneDigitalSyliusAdvancedTaxonPlugin/config/routes/admin.yaml'
```

The plugin exposes a single admin route, used by the conditions preview of the taxon form. The
storefront customizations are rendered by templates and Twig hooks, so they need no route.

## 3. Run database migrations

The plugin registers its Doctrine mapping and migrations automatically once the bundle is enabled.

Typical command:

```bash
php bin/console doctrine:migrations:migrate
```

Recent plugin migrations include:

- creation of translation tables for advanced taxon featured items and media fields;
- `include_children_products` on `sylius_taxon`;
- `show_card_text` on `sylius_taxon_image`.
- `show_customization_in_menu`, `show_customization_on_taxon_page`, `show_customization_in_breadcrumbs` on `sylius_taxon`.
- `advanced_filters_enabled` on `sylius_taxon`.

## 4. Schedule the conditional taxon synchronization

Conditional taxons are materialized by a Doctrine listener when the taxon is saved. Products that
become eligible later (new products, updated attributes, stock changes) are picked up by the
synchronization command:

```bash
php bin/console cyllene:advanced-taxon:sync-conditional-taxons
```

Run it once manually after installation, then schedule it in the background from the host
application. With crontab:

```bash
# every hour, in the background
0 * * * * cd /path/to/app && php bin/console cyllene:advanced-taxon:sync-conditional-taxons >> var/log/taxon-sync.log 2>&1
```

Or directly from a shell:

```bash
php bin/console cyllene:advanced-taxon:sync-conditional-taxons > var/log/taxon-sync.log 2>&1 &
```

For more advanced scheduling (overlapping prevention, dynamic schedules), install a scheduler such
as Symfony Scheduler in the host application and trigger the command from it.

## 5. Build assets

This plugin ships with both shop and admin JavaScript.
Build assets after installation and after each front-end change (Stimulus/SCSS included).

In a more traditional setup, build the test application assets and reinstall Symfony assets according to your host project workflow.

## 6. Clear the cache

```bash
php bin/console cache:clear
```

## 7. Pictogram upload directory

Taxon pictograms are stored as plain files in the **application** public directory, under
`public/media/advanced-taxon/icons`, and the taxon stores their public path
(`/media/advanced-taxon/icons/<file>`).

Make sure that directory is writable by the PHP user, or create it beforehand:

```bash
mkdir -p public/media/advanced-taxon/icons
```

Only image files are accepted: the extension is derived from the file content, and any unexpected
content falls back to `.png`.

## 8. Verify the installation

After installation you should see:

- extra tabs on the Taxon form in the Sylius back office;
- channel configuration exposing a mega menu toggle;
- storefront taxon pages rendering the plugin customizations when configured.

For conditional mass assignment specifically:

- saving a taxon with conditions attaches matching products;
- the synchronization command `cyllene:advanced-taxon:sync-conditional-taxons` is available and
  scheduled.

## Notes about existing taxon media

Advanced taxon media replaces the default Sylius taxon media form and storefront rendering while the plugin is enabled.
That replacement does **not** delete the original media records.

Concretely:

- the plugin stores advanced media on top of `sylius_taxon_image` entries;
- the standard Sylius taxon image UI is hidden while the plugin is active;
- removing the plugin does not erase those records from the database.

If you disable or remove the plugin, the base Sylius behavior can display the underlying taxon media again, provided your application goes back to the default taxon templates.
