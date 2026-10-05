# Sylius Advanced Taxon

[![License](https://img.shields.io/packagist/l/cyllene-digital/sylius-advanced-taxon-plugin)](LICENSE)
[![Latest version](https://img.shields.io/packagist/v/cyllene-digital/sylius-advanced-taxon-plugin)](https://packagist.org/packages/cyllene-digital/sylius-advanced-taxon-plugin)
[![Build](https://img.shields.io/github/actions/workflow/status/CylleneDigital/AdvancedTaxonPlugin/build.yaml?branch=main&label=build&logo=github)](https://github.com/CylleneDigital/AdvancedTaxonPlugin/actions/workflows/build.yaml)
[![Security](https://img.shields.io/github/actions/workflow/status/CylleneDigital/AdvancedTaxonPlugin/security.yaml?branch=main&label=security&logo=github)](https://github.com/CylleneDigital/AdvancedTaxonPlugin/actions/workflows/security.yaml)

<img src="docs/assets/banner.svg" alt="Sylius Advanced Taxon Plugin" width="100%">

A **Sylius plugin** that adds editorial and merchandising layers on top of taxons: **colors, icons and
pictograms**, **featured content**, **advanced media zones**, **conditional mass assignment** and an
optional **channel-level mega menu**.

## Compatibility

| Stack | Version |
| --- | --- |
| PHP | `^8.3` |
| Sylius | `^2.2 \|\| ^2.3` |
| Symfony | `^7.4 \|\| ^8.0` |

**Symfony 8 needs PHP 8.4+**: the Symfony 7.4 line still runs on PHP `^8.3`, while the Symfony `8.x`
line requires `^8.4`.

Runtime requirements for conditional taxon sync:

- the synchronization command must be scheduled by the host application, for example through crontab;
- PHP CLI must be able to boot the application and reach the database.

## Installation

```bash
composer require cyllene-digital/sylius-advanced-taxon-plugin
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
```

The Flex recipe registers the bundle, its configuration and its routes, and prints the remaining
steps ([`recipe/`](recipe)). Without Flex, register
`CylleneDigital\SyliusAdvancedTaxonPlugin\CylleneDigitalSyliusAdvancedTaxonPlugin` in
`config/bundles.php`.

The full installation guide is documented in [docs/installation.md](docs/installation.md).

## What this plugin adds to a taxon

Everything is configured per taxon, from `Catalog > Taxons` in the back office. The taxon form is
split into dedicated tabs so editorial work stays readable:

- **Display** - color, icon and pictogram, plus an optional colored background behind the universe
  page title.
- **Featured elements** - featured child taxons and featured products, placed before or after the
  native Sylius filters, with an optional slider mode (desktop navigation, mobile scroll hint).
- **Advanced medias** - media zones for top, bottom, left, right and mega menu contexts, rendered as
  product cards with dedicated placement modes (start, end, random) and per-media options. Random
  insertion re-rolls for each media item instead of injecting all media at the same index.
- **Mass assignment conditions** - the rules that turn a taxon into a conditional taxon, with a live
  preview of the matching product count and a sample list.
- **General** - `Include products from child taxons`, `Enable advanced filters on storefront`, `Is
  universe`.

On top of the taxon form, a channel gets a **`Has mega menu`** toggle, and the package configuration
lets you register icon libraries. The full configuration guide is documented in
[docs/configuration.md](docs/configuration.md).

## Conditional taxons

Conditional taxons are materialized as regular product↔taxon associations instead of being resolved
dynamically at query time. Define facet conditions on a taxon, save it, and matching products are
attached; only obsolete associations are detached and only new matches are attached, so positions of
products that stay assigned are preserved.

The synchronization is triggered by Doctrine once the taxon has been flushed, not by the admin form:
any saved conditional taxon is materialized, whichever entry point persisted it (admin, API,
fixtures, console). Condition operators are cumulative and follow a strict complement rule: `equals` +
`not_equals` (like `contains` + `not_contains` and `in` + `not_in`) always cover the whole enabled
catalog without overlap, so negative operators exclude any product owning a matching value. See
[Configuration](docs/configuration.md#condition-operators) for the operator matrix.

The plugin ships with the console command `cyllene:advanced-taxon:sync-conditional-taxons`, which
resynchronizes every conditional taxon. It is the entry point to run periodically, on a schedule
driven by the host application:

```bash
# every hour, in the background
0 * * * * cd /path/to/app && php bin/console cyllene:advanced-taxon:sync-conditional-taxons >> var/log/taxon-sync.log 2>&1
```

Useful commands:

```bash
php bin/console cyllene:advanced-taxon:sync-conditional-taxons
php bin/console list cyllene
```

## Advanced media override the base Sylius taxon media rendering

When the plugin is enabled, advanced media replaces the default Sylius taxon media user interface and
storefront rendering. That override does not delete your original data: taxon media entries remain
stored in `sylius_taxon_image`, removing the plugin does not purge those records, and reverting to the
default Sylius taxon templates lets a project expose the stored taxon media again. In short, the
plugin changes how taxon media is edited and rendered, not whether the underlying taxon media
survives.

## In production

- **Pictograms are stored in the application public directory.** An uploaded pictogram is written to
  `public/media/advanced-taxon/icons` of the application and served as a regular static asset, so the
  directory has to be writable by the PHP user.
- **The mega menu is enabled per channel.** It is not a single global switch: it is enabled on each
  channel through the channel form. This matters on multi-channel projects where one channel may need
  the plugin mega menu while another keeps the standard navigation.
- **Conditional taxon sync has to be scheduled.** Materialized associations drift over time as the
  catalog changes; the console command is what keeps them synchronized. For more advanced scheduling
  (overlapping prevention, dynamic schedules), install a scheduler such as Symfony Scheduler in the
  host application and trigger the command from it.

## Status

The plugin already covers the main editorial and storefront taxonomy customizations needed by
content-heavy Sylius projects: richer taxon branding through colors and icons, configurable featured
content blocks, conditional merchandising through facet-driven mass assignment, advanced media
placement in several storefront zones, and optional mega menu behavior at channel level.

Because this plugin changes both data handling and rendering of taxons, each project should validate
its final Twig integration, asset build and editorial workflow after installation.

## Contributing

[docs/contributing.md](docs/contributing.md) - feature map, file ownership areas, Stimulus/SCSS
conventions, local workflow and the validation checklist.

A flaw is reported privately: [SECURITY.md](SECURITY.md). Do not open it as a public issue.

## Provenance and licence

Released under the **MIT** licence - see [LICENSE](LICENSE).

---

Package: [`cyllene-digital/sylius-advanced-taxon-plugin`](https://packagist.org/packages/cyllene-digital/sylius-advanced-taxon-plugin)

Maintained by [Cyllene](https://www.groupe-cyllene.com/), on GitHub as [@CylleneDigital](https://github.com/CylleneDigital)
