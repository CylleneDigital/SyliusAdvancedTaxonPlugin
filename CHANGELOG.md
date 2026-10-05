# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-01

First release of the plugin.

### Added

- Advanced taxon customization in admin and storefront.
- Taxon visual customization: color, icon selection, pictogram upload.
  - pictograms are stored as plain files under `public/media/advanced-taxon/icons` of the application
    and served as regular static assets;
  - the stored extension is derived from the uploaded file content, with a `.png` fallback, and
    oversized uploads are resized before being stored.
- Dedicated taxon admin tabs for display, featured items, advanced medias, and mass assignment conditions.
  - the advanced media zone fields are generated from a single declarative definition;
  - the tab list and the native taxon tree share a single sticky rail, so the tree stays visible
    instead of scrolling under the tabs.
- Featured child taxons and featured products management.
- Per-taxon toggle applying the taxon color as a background behind the universe page title.
- Featured products placement controls relative to storefront filters.
- Featured products slider mode with desktop navigation and mobile horizontal behavior.
- Advanced media zones: top, bottom, left, right, mega menu, and universe slider.
- Right-zone media insertion into product lists with position modes: start, end, random middle.
- Media card display options: image only, or image with title and description.
  - the card text toggle is only offered for media of the product card zone, since it is the only
    zone rendering media as product-like cards inside the product listing;
  - the position of a new media is pre-filled with the highest position already used in its zone,
    so it is appended after the media already entered.
- Clickable media cards when a destination URL is configured.
- Optional inclusion of products from child taxons in storefront taxon listings.
- Storefront grid and list display switch on taxon product pages.
- Storefront advanced filters toggle with dynamic facets and product counts.
  - facets cover attributes, options, sub-taxons and price, with per-value product counts computed
    with the active filters applied, and multi-select values within each facet family;
  - price facets and price filters are scoped to the current channel;
  - facet lists are capped at 150 pixels with an internal scroll and expose a per-list search field,
    which is diacritics insensitive, never submitted with the filter form, and keeps checked values
    selected when a search hides them.
- Conditional taxons based on cumulative facet conditions on product attributes, options, name,
  description, stock availability, and membership in another taxon.
  - conditions use the `equals`, `not_equals`, `contains`, `not_contains`, `in`, `not_in` and
    `is_in_stock` operators; negative operators are compiled to `NOT EXISTS` subqueries, so a negative
    operator is the exact complement of its positive counterpart, including for products owning
    several values;
  - attribute comparisons read both the `text` and `json` fields, covering select and multi-select
    attributes.
- Admin preview for conditional taxon conditions with matching products count and sample list.
- Conditional taxon status automatically derived from configured conditions.
- Materialized product to taxon sync for conditional taxons, triggered by Doctrine once the taxon has
  been flushed.
  - the synchronization also applies to taxons saved outside of the admin form (API, fixtures, custom
    console commands) and never runs for an invalid form submission;
  - a full synchronization processes taxons by batches, keeping memory bounded on large catalogs.
- Differential conditional sync that detaches obsolete links, attaches new links, and preserves positions for products that remain assigned.
- Manual synchronization command: cyllene:advanced-taxon:sync-conditional-taxons.
- Background synchronization: the command is meant to be scheduled from the host application, for
  example through crontab.
- Channel-level mega menu toggle and storefront mega menu behavior.
- Auto-discovery of Tabler icon set and support for additional icon libraries from plugin configuration.
- Native Sylius style translations for featured taxon content and taxon media metadata, resolved
  without creating empty translations on read.
- Plugin migrations registered under the `CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations`
  namespace, executed after the Sylius core migrations, and portable across MySQL, MariaDB and
  PostgreSQL.
- Developer facing additions:
  - `ConditionalTaxonSynchronizerInterface`, implemented by `ConditionalTaxonProductAssigner`, so the
    console command depends on a contract instead of a concrete service;
  - `TaxonIconUploader`, responsible for pictogram storage in the application public directory;
  - `ConditionalTaxonSyncListener`, the Doctrine listener materializing conditional taxons on flush;
  - plugin form types declaring their `extra_options` explicitly, and `FacetProductQueryBuilder`
    requiring an explicit locale code with no implicit default.
- Quality and tests:
  - unit tests covering facet condition operators, taxon display mode normalization, pictogram upload
    and image resizing;
  - Behat suites covering the admin taxon form (color, icon, pictogram upload, media zones card text
    option and pre-filled positions) and the materialization
    of conditional taxons, including the synchronization command;
  - a test application able to boot in the `test` environment;
  - PHPStan at max level and ECS covering `src`, `tests/Behat` and `tests/Unit`, without blanket
    ignores.
