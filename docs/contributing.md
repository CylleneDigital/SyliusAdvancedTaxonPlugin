# Contributing Guide

## Overview

This plugin customizes Sylius taxons across admin and storefront.

Main domains:

- taxon visual customization (color, icon, pictogram);
- featured content (featured child taxons, featured products);
- advanced media zones and media-as-product cards;
- conditional taxons, facet conditions, and mass assignment sync;
- storefront advanced filters (taxon-level toggle).

## Project map

### Domain model and persistence

- Taxon entity: src/Entity/Taxon.php
- Taxon media entity: src/Entity/TaxonImage.php
- Media translations: src/Entity/TaxonImageTranslation.php
- Conditional conditions entity: src/Entity/FacetCondition.php
- Migrations: src/Migrations/

### Business/query services

- Facet and storefront filter queries: src/Service/FacetProductQueryBuilder.php
- Conditional taxon mass assignment sync: src/Service/ConditionalTaxonProductAssigner.php
- Synchronizer contract consumed by the command and the message handler:
  src/Service/ConditionalTaxonSynchronizerInterface.php
- Pictogram storage in the application public directory: src/Service/TaxonIconUploader.php
- Image resize helper: src/Service/UploadedImageResizer.php

### Persistence side effects

The conditional taxon synchronization is triggered by a Doctrine event listener
(`src/EventListener/ConditionalTaxonSyncListener.php`), not by the admin form: taxons are collected
during `onFlush` and synchronized during `postFlush`, so only taxons that really reached the database
are processed, whatever the entry point (admin, API, fixtures, console).

The synchronizer owns its flush cycles during a full synchronization
(`syncAllConditionalTaxons()`) and never flushes during a single taxon sync (`syncTaxon()`): the
listener batches the flush of the whole unit of work in that case.

### Automation

- Synchronization command, to run on a schedule from the host application:
  src/Command/SyncConditionalTaxonsCommand.php

### Form extensions and types

- Taxon admin extension: src/Form/Extension/TaxonTypeExtension.php
- Channel admin extension: src/Form/Extension/ChannelTypeExtension.php
- Taxon media and translation forms: src/Form/Type/

### Twig extensions and components

- Twig functions bridge: src/Twig/FacetExtension.php

### Twig hooks configuration

- Storefront hooks: config/twig_hooks/shop.yaml
- Admin hooks: config/twig_hooks/admin.yaml

### Service wiring and DI

- Plugin services are loaded by namespace from `src/` with autowire + autoconfigure in config/services.yaml.
- Most tags are declared directly on classes with Symfony attributes:
  - `#[AsController]` for controllers;
  - `#[AutoconfigureTag('form.type')]` for form types;
  - `#[AutoconfigureTag('form.type_extension', ['extended-type' => ...])]` for form type extensions;
  - `#[AutoconfigureTag('twig.extension')]` for Twig extensions;
  - `#[AsCommand]` for console commands.
- Use `#[Autowire(...)]` for non-trivial injections (named services like Sylius repositories/factories or scalar parameters).
- Keep config/services.yaml minimal and avoid adding explicit per-service declarations unless a specific edge case requires it.

### Templates

- Admin taxon form sections: templates/admin/taxon/sections/form/
- Storefront taxon listing: templates/shop/product/index/content/
- Sylius bundle overrides: templates/bundles/SyliusShopBundle/
- Shared label component: templates/components/advanced_taxon/taxon_label.html.twig

### Frontend assets

- Admin Stimulus entrypoint: assets/admin/entrypoint.js
- Admin Stimulus controllers: assets/admin/js/
- Admin SCSS: assets/admin/scss/
- Shop Stimulus entrypoint: assets/shop/entrypoint.js
- Shop Stimulus controllers: assets/shop/js/
- Shop SCSS: assets/shop/scss/

## Stimulus and CSS conventions

- Do not add inline script blocks in Twig templates.
- Prefer dedicated Stimulus controllers in assets/admin/js or assets/shop/js.
- Register controllers in the relevant entrypoint.
- Avoid inline styles for static layout rules.
- Put reusable styles in SCSS files under assets/admin/scss or assets/shop/scss.

Current custom Stimulus controllers:

- admin:
  - assets/admin/js/taxon-icon-picker-controller.js
  - assets/admin/js/taxon-facet-conditions-controller.js
  - assets/admin/js/form-collection-controller.js
- shop:
  - assets/shop/js/mobile-menu-controller.js
  - assets/shop/js/mega-menu-controller.js
  - assets/shop/js/featured-products-slider-controller.js
  - assets/shop/js/advanced-filters-controller.js

## Storefront advanced filters

Feature summary:

- Taxon-level toggle: advanced_filters_enabled
- Shows All filters button in filter controls
- Shows left sidebar facets when enabled
- Supports multi-select filters:
  - attributes
  - options
  - sub-taxons
  - price range (min/max)
- Facet counts are computed with active filters applied.

Key files:

- Toggle and persistence:
  - src/Entity/Taxon.php
  - src/Form/Extension/TaxonTypeExtension.php
  - src/Migrations/Version20260828173000.php
- Facet/filter query logic:
  - src/Service/FacetProductQueryBuilder.php
  - src/Twig/FacetExtension.php
- Hook and UI templates:
  - config/twig_hooks/shop.yaml
  - templates/shop/product/index/content/body/main/filters/controls/all_filters.html.twig
  - templates/shop/product/index/content/body/sidebar/facet_filters.html.twig
  - templates/shop/product/index/content/body/main/products.html.twig

## Validation checklist before PR

- Run PHPStan at max level (`vendor/bin/phpstan analyse -c phpstan.neon -l max`).
- Run ECS (`vendor/bin/ecs check`).
- Run the unit test suite (`vendor/bin/phpunit --testsuite=unit`).
- Run the Behat suites (`vendor/bin/behat --no-interaction --tags="~@javascript"`).
- Run Twig lint on edited templates.
- Run YAML lint on edited config/translations.
- If entity mappings changed, run migrations and verify schema (`doctrine:schema:validate`).
- Rebuild assets if JS/SCSS changed.

PHPStan and ECS both cover `src`, `tests/Behat` and `tests/Unit`.

## Test application and Behat

The Symfony kernel used by PHPUnit and Behat boots `tests/TestApplication/config/services_test.php`,
which imports `@CylleneDigitalSyliusAdvancedTaxonPlugin/tests/Behat/Resources/services.xml`.
That file must exist for the kernel to boot in the `test` environment; declare there any service used
by Behat contexts, with `<defaults public="true" />` since Behat resolves contexts from the
container.

Behat suites live in `tests/Behat/Resources/suites.yml` and are filtered by tags:

| Suite | Tag filter | What it covers |
| --- | --- | --- |
| `advanced_taxon_ui` | `@advanced_taxon&&@ui` | admin taxon form: colour, icon, pictogram upload, media zones (card text option, pre-filled positions) |
| `advanced_taxon_conditional` | `@advanced_taxon&&@conditional` | conditional taxon materialization and the synchronization command |

Run them with:

```bash
# whole suite (no browser required)
vendor/bin/behat --no-interaction --tags="~@javascript"

# a single suite
vendor/bin/behat --suite=advanced_taxon_conditional
```

Behat runs against the `test` database, which must exist and be migrated first:

```bash
vendor/bin/console doctrine:database:create --env=test --if-not-exists
vendor/bin/console doctrine:migrations:migrate -n --env=test
```

The `doctrine_orm` hook purges the database before every scenario, it does not create the schema.

Note about the admin form: the plugin renders its own translations section on the taxon form, so the
native Sylius taxon form element cannot be used to fill the localized name and slug. The
`ManagingAdvancedTaxonContext` fills those fields by name instead.

## Feature entry points for contributors

If you want to contribute on a specific feature:

- Admin taxon UX:
  - templates/admin/taxon/sections/form/
  - assets/admin/js/
  - assets/admin/scss/
- Mega menu and mobile navigation:
  - templates/bundles/SyliusShopBundle/shared/layout/base/header/navbar/
  - assets/shop/js/mega-menu-controller.js
  - assets/shop/js/mobile-menu-controller.js
  - assets/shop/scss/mega-menu.scss
- Product listing media cards:
  - templates/shop/product/index/content/body/main/products/
  - src/Entity/TaxonImage.php
  - src/Form/Type/TaxonMediaType.php
- Conditional mass-assignment conditions and preview:
  - templates/admin/taxon/sections/form/advanced_taxon/facet_conditions.html.twig
  - src/Controller/AdminFacetPreviewController.php
  - assets/admin/js/taxon-facet-conditions-controller.js
- Storefront advanced filters:
  - src/Service/FacetProductQueryBuilder.php
  - templates/shop/product/index/content/body/sidebar/facet_filters.html.twig
  - templates/shop/product/index/content/body/main/products.html.twig
