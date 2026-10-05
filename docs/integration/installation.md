# Installation

This page installs the plugin in an existing Sylius 2 application. Every step applies to a Flex and
a non-Flex installation, except step 2.

## Compatibility

| Component | Versions |
|---|---|
| PHP | `^8.3` (`^8.4` with Symfony 8) |
| Sylius | `^2.1` (tested on `2.1`, `2.2` and `2.3`) |
| Symfony | `^7.4`, or `^8.0` with Sylius 2.3 |
| Database | MySQL 8.0 / 8.4, MariaDB 10.11 / 11.4, PostgreSQL 15 to 17 (CI matrix) |

MariaDB needs a MySQL `serverVersion`: see
[Troubleshooting](troubleshooting.md#the-database-only-holds-the-cyllene_advanced_taxon_-tables).

## Requirements

Installed by Composer with the plugin (`composer.json`):

| Package | Constraint | Used for |
|---|---|---|
| `ext-gd` | `*` | Downscaling uploaded media before Sylius stores them (`UploadedImageResizer`) |
| `liip/imagine-bundle` | `^2.15` | Pictogram and mobile slider filter sets |
| `symfony/ux-autocomplete` | `^2.36 \|\| ^3.0` | Featured children and featured products fields of the taxon form |
| `symfony/ux-icons` | `^2.36 \|\| ^3.0` | `ux_icon()` calls in the templates (see [Assets](assets.md#icons)) |
| `symfony/messenger` | `^7.4 \|\| ^8.0` | `SynchronizeConditionalTaxon` message, dispatched on `sylius.command_bus` |
| `sylius/twig-hooks` | `^0.8 \|\| ^0.9 \|\| ^0.12 \|\| ^0.14` | Every admin and shop customization; the version follows Sylius (0.8 with 2.1, 0.9 with 2.2, 0.12 or 0.14 with 2.3) |
| `doctrine/doctrine-migrations-bundle` | `^3.7` | The plugin migration |

They all come with Sylius already: the plugin declares them because it uses them directly. On the
JavaScript side, the entrypoints only need what `@sylius-ui/admin` and `@sylius-ui/shop` already bring
([build requirements](assets.md#build-requirements)).

You also need a database user allowed to run Doctrine migrations, and a way to run a console command
on a schedule (crontab, Symfony Scheduler, a PaaS cron).

## 1. Require the package

```bash
composer require cyllene-digital/sylius-advanced-taxon-plugin
```

The Flex recipe lives in [`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib),
under `cyllene-digital/sylius-advanced-taxon-plugin`. Symfony Flex applies step 2 for you when
contrib recipes are allowed:

```bash
composer config extra.symfony.allow-contrib true
composer require cyllene-digital/sylius-advanced-taxon-plugin
```

| Recipe file | Effect |
|---|---|
| `manifest.json` | Registers the bundle in `config/bundles.php` for all environments |
| `config/packages/cyllene_digital_sylius_advanced_taxon.yaml` | Imports `@CylleneDigitalSyliusAdvancedTaxonPlugin/config/config.yaml`, with the optional settings commented |
| `config/routes/cyllene_digital_sylius_advanced_taxon.yaml` | Imports the admin routes |
| `post-install.txt` | Prints the remaining steps (3 to 6 below) |

The recipe does not touch your entities, your assets or your database. Continue at step 3.

## 2. Without Flex

### Register the bundle

```php
// config/bundles.php
return [
    // ...
    CylleneDigital\SyliusAdvancedTaxonPlugin\CylleneDigitalSyliusAdvancedTaxonPlugin::class => ['all' => true],
];
```

### Import the plugin configuration

```yaml
# config/packages/cyllene_digital_sylius_advanced_taxon.yaml
imports:
    - { resource: "@CylleneDigitalSyliusAdvancedTaxonPlugin/config/config.yaml" }
```

This import is **required**. `config/config.yaml` loads `config/twig_hooks/admin.yaml` and
`config/twig_hooks/shop.yaml`, which plug every template of the plugin into Sylius. Without it, the
bundle is enabled but nothing shows in the admin or the shop.

It must be loaded **after** the Sylius configuration (`config/packages/_sylius.yaml`): the plugin
redefines hookables declared by Sylius, and the last definition wins. A file in `config/packages/`
whose name sorts after `_sylius.yaml` (as above) is enough. Importing it from `_sylius.yaml` itself,
or from a file loaded before it, lets Sylius restore its own templates.

The plugin does **not** declare your Sylius models: step 3 does.

### Import the routes

```yaml
# config/routes/cyllene_digital_sylius_advanced_taxon.yaml
cyllene_digital_sylius_advanced_taxon_admin:
    resource: "@CylleneDigitalSyliusAdvancedTaxonPlugin/config/routes.yaml"
```

It declares a single route, `cyllene_digital_sylius_advanced_taxon_admin_facet_preview`
(`POST /%sylius_admin.path_name%/advanced-taxon/facet-conditions/preview`), used by the conditions
preview of the taxon form. The path follows a customized back-office path. The shop needs no route.

Doctrine mapping, migrations, services, validation and translations need no configuration: the
bundle registers them.

## 3. Extend the Sylius entities

The plugin does not replace the Sylius `Taxon`, `Channel` and `TaxonImage` models. It ships one
interface and one trait per model, to use in your own entities. An entity your application (or
another plugin) already extends only gains the trait and the interface.

| Sylius model | Interface | Trait | Constructor call |
|---|---|---|---|
| `Taxon` | `AdvancedTaxonInterface` | `AdvancedTaxonTrait` | `$this->initializeAdvancedTaxon()` |
| `Channel` | `MegaMenuChannelInterface` | `MegaMenuChannelTrait` | none |
| `TaxonImage` | `AdvancedTaxonImageInterface` | `AdvancedTaxonImageTrait` | `$this->initializeAdvancedTaxonImage()` |

All of them live in the `CylleneDigital\SyliusAdvancedTaxonPlugin\Entity` namespace. The traits
carry their Doctrine mapping as attributes: your entities must be mapped with attributes too (the
Sylius 2 default).

```php
<?php
// src/Entity/Taxonomy/Taxon.php

declare(strict_types=1);

namespace App\Entity\Taxonomy;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon')]
class Taxon extends BaseTaxon implements AdvancedTaxonInterface
{
    use AdvancedTaxonTrait;

    public function __construct()
    {
        parent::__construct();

        $this->initializeAdvancedTaxon();
    }
}
```

```php
<?php
// src/Entity/Channel/Channel.php

declare(strict_types=1);

namespace App\Entity\Channel;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\MegaMenuChannelInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\MegaMenuChannelTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Channel as BaseChannel;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_channel')]
class Channel extends BaseChannel implements MegaMenuChannelInterface
{
    use MegaMenuChannelTrait;
}
```

```php
<?php
// src/Entity/Taxonomy/TaxonImage.php

declare(strict_types=1);

namespace App\Entity\Taxonomy;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\TaxonImage as BaseTaxonImage;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon_image')]
class TaxonImage extends BaseTaxonImage implements AdvancedTaxonImageInterface
{
    use AdvancedTaxonImageTrait;

    public function __construct()
    {
        $this->initializeAdvancedTaxonImage();
    }
}
```

`initializeAdvancedTaxon()` creates the featured children, featured products, facet conditions and
featured items translations collections. `initializeAdvancedTaxonImage()` creates the translations
collection of the media. An entity that already has a constructor only needs the call added to it.

`AdvancedTaxonImageTrait` uses the Sylius `TranslatableTrait`: a `TaxonImage` that is already
translatable by other means conflicts with it.

Declare the classes as the Sylius models, if your application does not already:

```yaml
# config/packages/_sylius.yaml
sylius_taxonomy:
    resources:
        taxon:
            classes:
                model: App\Entity\Taxonomy\Taxon

sylius_channel:
    resources:
        channel:
            classes:
                model: App\Entity\Channel\Channel

sylius_core:
    resources:
        taxon_image:
            classes:
                model: App\Entity\Taxonomy\TaxonImage
```

The test application of the plugin is a working example: `tests/TestApplication/src/Entity/` and
`tests/TestApplication/config/config.yaml`.

## 4. Migrate the database

```bash
bin/console doctrine:migrations:migrate
```

The plugin ships one migration, `CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000`,
registered automatically and ordered after the Sylius core migrations. It:

- adds the plugin columns to `sylius_taxon`, `sylius_channel` and `sylius_taxon_image`;
- creates the `cyllene_advanced_taxon_featured_children`, `cyllene_advanced_taxon_featured_products`,
  `cyllene_advanced_taxon_facet_condition`, `cyllene_advanced_taxon_featured_items_translation` and
  `cyllene_advanced_taxon_image_translation` tables.

It only adds the columns and tables that are missing, so it runs on top of a schema your own
entities already extended, and can be replayed. The DDL is generated by Doctrine DBAL for the current
platform (MySQL, MariaDB, PostgreSQL). Details: [Schema](../persistence/schema.md),
[Migrations](../persistence/migrations.md).

If `sylius_core.prepend_doctrine_migrations` is `false` in your application, the migration is not
registered: see [Troubleshooting](troubleshooting.md#the-plugin-migration-is-not-listed).

### On a shop already in production

The taxon form loses the native **Images** section; its media zones take over:

- a taxon image of type `main` or without type (as Sylius stores it) is listed in the **Main image**
  zone, and still shown on the taxon page;
- the types `main`, `top`, `bottom`, `left`, `right`, `featured` and `slider_universe` are the plugin
  zones: an image of your own with one of them shows in that zone;
- images of any other type stay in the database and on the pages of your theme that use them, but
  cannot be edited from the taxon form any more;
- a template, API consumer or feed that reads `taxon.images` without filtering on the type will also
  see the plugin media (banners, product cards): filter on the type it expects.

## 5. Build the assets

Import the plugin admin and shop entrypoints from yours, then rebuild your assets (`yarn build`):
[Assets](assets.md#import-the-entrypoints).

## 6. Keep conditional taxons in sync

The products of a conditional taxon are assigned when its conditions change
([how](configuration.md#conditional-taxon-synchronization-messenger)). Products that become eligible
later (new products, changed attributes, options or stock) are only picked up by the synchronization
command. Run it once after installation, then on a schedule:

```bash
bin/console cyllene:advanced-taxon:sync-conditional-taxons
```

```bash
# crontab: every hour
0 * * * * cd /path/to/app && php bin/console cyllene:advanced-taxon:sync-conditional-taxons >> var/log/taxon-sync.log 2>&1
```

The command has no option. It prints the number of taxons, matched products, attached and detached
links.

On a large catalog, route the synchronization message to an asynchronous transport:
[Configuration](configuration.md#conditional-taxon-synchronization-messenger).

## 7. Verify

```bash
bin/console cache:clear
bin/console debug:router cyllene_digital_sylius_advanced_taxon_admin_facet_preview
bin/console doctrine:schema:validate --skip-sync
bin/console sylius:debug:twig-hooks sylius_admin.taxon.update.content.sections.form   # Sylius 2.3
```

Then, in the back office:

- `Catalog > Taxons`, edit a taxon: a tab list (`General`, `Advanced customizations`,
  `Featured elements`, `Advanced media`, `Mass-assignment conditions`, `Universe`) sits above the
  taxon tree;
- `Configuration > Channels`, edit a channel: the `Look & feel` section has an `Enable mega menu`
  toggle;
- in the `Mass-assignment conditions` tab, the preview returns a product count.

In the shop, a taxon page renders the view mode switch (grid / list) next to the native sorting and
limit controls.

If one of these is missing, see [Troubleshooting](troubleshooting.md).

## Next

- [Configuration](configuration.md): bundle options, image filters, Messenger, product grid.
- [Theming](theming.md): Twig hooks the plugin adds, replaces or disables.
- [Features](../features.md) and [Admin user guide](../admin/user-guide.md).
