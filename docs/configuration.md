# Configuration

## Overview

The plugin extends Sylius taxons in five main areas:

- visual customization: color, icon, pictogram;
- featured content: featured child taxons and featured products;
- advanced media by display zone;
- conditional taxons driven by facet conditions and mass assignment;
- universe pages: a dedicated full-width storefront layout for showcase taxons.

It also extends channels with a mega menu toggle.

## Feature map (where to find each capability)

- Admin > Catalog > Taxons > create/edit taxon:
    - `General`: `Include products from child taxons`, `Enable advanced filters on storefront`;
    - `Display`: color, icon, pictogram upload, universe title background toggle;
    - `Featured elements`: featured child taxons, featured products, placement, slider;
    - `Advanced medias`: top/bottom/left/right/featured media zones;
    - `Mass assignment conditions`: conditional rules and preview;
    - `Universe`: universe mode, universe background, universe hero slider, universe translations.
- Admin > Channels > create/edit channel:
    - `Has mega menu` toggle.
- App configuration file:
    - `config/packages/cyllene_digital_sylius_advanced_taxon.yaml` for icon libraries.

## Taxon form

Open a taxon in the Sylius back office and use the dedicated tabs:

- `General`: native Sylius taxon fields and translations;
- `Display`: color, icon library glyph, or pictogram upload, the visibility toggles, and the toggle applying the taxon color as the background of the universe page title;
- `Featured elements`: featured child taxons, featured products, featured products placement, slider toggle;
- `Advanced medias`: media blocks for top, bottom, left, right, and featured zones;
- `Mass assignment conditions`: facet conditions and preview tools;
- `Universe`: universe mode toggle, universe background option, universe hero slider, and universe translations.

The tab list and the native Sylius taxon tree share the left column of that page. They are kept in a
single sticky rail, scrolling together when the rail does not fit in the viewport, so the tree never
slides under the tab list.

## Icons and pictograms

### Built-in icons

The plugin discovers Tabler icons automatically from the installed Sylius UI assets.
No extra configuration is required to use them.

### Additional icon libraries

The plugin can also expose icon libraries registered through `symfony/ux-icons`.
The `icon_prefix` value must match the prefix provided by the UX Icons set you installed in your project.

For example, if your project uses Phosphor through Symfony UX Icons, you can declare a dedicated picker section like this:

```yaml
# config/packages/cyllene_digital_sylius_advanced_taxon.yaml
cyllene_digital_sylius_advanced_taxon:
    icon_libraries:
        phosphor:
            label: 'Phosphor'
            icon_prefix: 'ph'
            icons:
                - 'star'
                - 'heart'
                - 'tree-evergreen'
```

This assumes the `ph:*` icons are already available in your Symfony application through UX Icons.

Fields:

- `label`: optional human-readable label shown in the icon picker;
- `icon_prefix`: the UX Icons prefix used to build the icon identifier;
- `icons`: the list of icons available in the picker.

### Pictogram storage

An uploaded pictogram is written to `public/media/advanced-taxon/icons` of the application and the
taxon stores its public path (`/media/advanced-taxon/icons/<file>`), so it is served as a regular
static asset.

The stored extension is derived from the uploaded file content, never from its name; unexpected
content falls back to `.png`. Serve that directory with the caching headers you use for the rest of
your media.


## Background conditional sync

Conditional taxons are materialized when the taxon is saved. To keep assignments up to date as
products change (new products, updated attributes, stock changes), run the synchronization command on
a schedule from the host application:

```bash
php bin/console cyllene:advanced-taxon:sync-conditional-taxons
```

Example crontab entry (every hour, in the background):

```bash
0 * * * * cd /path/to/app && php bin/console cyllene:advanced-taxon:sync-conditional-taxons >> var/log/taxon-sync.log 2>&1
```

For more advanced scheduling (overlapping prevention, dynamic schedules), install a scheduler such
as Symfony Scheduler in the host application and trigger the command from it.

## Featured products

The taxon form lets you configure featured products independently from the normal taxon product listing.

Options available:

- enable or disable featured products rendering;
- choose whether they are rendered before or after the native Sylius filters on the taxon page;
- render them as a slider or as a regular grid.

On the storefront:

- desktop slider mode includes previous and next buttons;
- mobile slider mode displays a horizontal scroll hint.

## Product list mode on taxon pages

Taxon pages expose a display mode switch next to the native Sylius filter controls.
Customers can switch between:

- grid view;
- list view.

This works for both:

- standard taxons using the normal Sylius product listing;
- conditional taxons (assigned products materialized from facet conditions).

For standard taxons, an additional taxon-level toggle is available:

- `Include products from child taxons`.

When enabled, the storefront list also includes products assigned to descendant taxons.

## Storefront advanced filters

The taxon form now exposes `Enable advanced filters on storefront`.

When enabled on a taxon:

- the filter controls bar shows an `All filters` button;
- the left sidebar is populated with dynamic facets;
- each facet value displays the matching product count with active filters applied.

Supported facets:

- attributes (multi-select values);
- options (multi-select values);
- sub-taxons (multi-select);
- price range (min/max).

Each facet list is capped at 150 pixels high and scrolls internally, so a taxon with many values
keeps the whole panel readable. Every list also has a search field filtering its values as you type
(diacritics insensitive). The search field is not submitted with the form, and a value checked before
searching stays selected even when the search hides it.

Prices are channel scoped in Sylius: price facets and price filters only aggregate the channel
pricings matching the current channel code. When no channel is resolved (CLI contexts such as the
conditional taxon synchronization command), every channel pricing is considered instead.

The storefront listing query is rebuilt using the selected filters, and multi-selection is supported within each facet family.

## Advanced medias

Advanced medias are organized by zone:

- top;
- bottom;
- left;
- product;
- featured: the spotlight zone, also used for the universe page category cards;
- universe slider (`slider_univers`), configured from the `Universe` tab.

Each zone exposes its media collection and, except the universe slider, a display mode:

- `stacked` / `random` (top, bottom, left, featured): order the zone media by position, or shuffle
  them. On a single-image zone such as the spotlight (`featured`), `random` picks one of the media at
  random on every render, and `stacked` keeps the one with the lowest position;
- `products_start` / `products_end` / `products_random_middle` (product):
  where to inject the media as product cards inside the product listing.

The display mode is persisted on the taxon for the top, bottom, left, product and featured zones.
The universe slider has none: it always renders its media as a slider.

### Right zone as product cards

The right zone is designed to inject media into the product listing as product-like cards.

Available insertion modes:

- `products_start`;
- `products_end`;
- `products_random_middle`.

In random mode, the insertion position is recalculated for each media item.

### Media card content options

The product card zone is the only zone whose media are rendered as product-like cards inside the
product listing, so it is also the only zone exposing the card content option:

- image only;
- image with title and description.

Media of the other zones never offer that option: they are rendered as plain visuals by their own
zone rendering.

When a destination URL is set on the media item, the whole card is clickable (not only the image).

### Media positions

The position of a media orders the zone media on the storefront. When a media is added to a zone,
its position field is pre-filled with the highest position already used by that zone, incremented by
one, so the new media is appended after the media already entered. Positions already stored on
existing media are never overwritten by that default.

### Important behavior

Advanced medias **override the base Sylius taxon media rendering** while the plugin is active.
This is intentional: the plugin replaces the default taxon image form and uses zone-aware rendering on the storefront.

That override does **not** mean data loss:

- the original `sylius_taxon_image` records are not deleted when the plugin is removed;
- disabling the plugin does not wipe existing media rows;
- once the plugin-specific templates are no longer used, a project can revert to the base Sylius rendering of stored taxon media.

## Universe mode

A taxon can be turned into a **universe**: a dedicated, full-width storefront page meant for showcase
sections (a brand, a theme, a season) rather than a classic product listing.

It is enabled per taxon with the `This taxon is a universe` toggle (`is_universe` column) in the
`Universe` tab, whose label reads `Universe` (`Mode univers` in French).

### Storefront behavior

When a taxon is a universe, its page switches to the dedicated universe template:

- no left column and no facet sidebar;
- no standard product listing, no pagination and no advanced filter controls;
- the standard advanced media zones (top, left, right) and the standard featured products block are
  not rendered.

The universe page is composed of, in order:

1. a **hero slider** built from the `slider_univers` media zone, with one optional destination URL per
   media. It autoplays (5 seconds) and shows previous/next controls as soon as it holds more than one
   media;
2. an **intro card** with the universe page title and description;
3. a **children navigation** grid: one card per enabled child taxon, using the child color when set and
   the first `featured` (spotlight) media as the card image, plus the child's own enabled children as
   shortcuts;
4. a **featured children** area: for every child taxon holding featured products, the child featured
   products slider followed by a call-to-action button linking to the child taxon;
5. a **bottom media** block, reusing the `bottom` zone of the taxon.

If the taxon has no enabled child, the page only displays a "no results" message.

### Universe content

The `Universe` tab groups:

- `This taxon is a universe` (`is_universe`);
- `Use the taxon color as universe page background` (`universe_background_use_taxon_color`) - applies
  the taxon color as a decorative background of the whole page; ignored when the taxon has no color;
- the **universe hero slider** media zone (`slider_univers`);
- an **Universe translations** accordion, per locale, with:
    - `universe_page_title` - falls back to the taxon name when left empty;
    - `universe_page_description`;
    - `universe_featured_products_title` - title of the featured children block;
    - `universe_featured_products_description`.

The `Display` tab also carries `Apply a colored background behind the universe page title`
(`universe_title_background_enabled`), which tints the intro card with the taxon color.

### Mobile hero slider

The hero slider serves a lighter image below `768px`, through the `cyllene_universe_slider_mobile`
LiipImagine filter set (default `640x360`, `outbound`, quality `82`). It is declared by the plugin
recipe; override the filter set in the application configuration to match the design's format:

```yaml
# config/packages/cyllene_digital_sylius_advanced_taxon.yaml
liip_imagine:
    filter_sets:
        cyllene_universe_slider_mobile:
            filters:
                thumbnail:
                    size: [640, 360]
                    mode: outbound
                strip: ~
                quality:
                    quality: 82
```

If the filter set is missing, the slider falls back to the desktop image.

## Conditional taxons and mass assignment

A conditional taxon uses facet conditions to automatically attach matching products.
Assignments are materialized as regular product↔taxon links.

Status behavior in admin:

- the conditional status checkbox is informational and read-only;
- it is computed automatically: at least one condition means conditional taxon;
- no manual toggle is required.

The materialization itself is driven by Doctrine: assignments are synchronized once the taxon has
effectively been flushed, so it also applies to taxons saved outside of the admin form (API,
fixtures, custom console commands).

Supported condition families include:

- product attributes;
- product options;
- product name;
- product description;
- stock availability;
- membership in another taxon.

All configured conditions are cumulative.

### Condition operators

Each condition combines a type with an operator:

| Condition type | Available operators |
| --- | --- |
| Attribute | `equals`, `not_equals`, `contains`, `not_contains` |
| Option | `in`, `not_in` |
| Name | `equals`, `not_equals`, `contains`, `not_contains` |
| Description | `contains`, `not_contains` |
| Stock | `is_in_stock` |
| Taxon membership | `in`, `not_in` |

Negative operators mean **the product owns no matching value**, not *the product owns at least one
value that differs*. The distinction matters as soon as a product carries several values, which is
the normal case for options and taxons:

- `not_in` on an option excludes every product having a variant with the excluded option value;
- `not_in` on a taxon excludes every product assigned to the excluded taxon;
- `not_equals` / `not_contains` on an attribute exclude every product owning a matching value, and
  keep products that do not carry the attribute at all.

As a consequence, for any given value, `equals` + `not_equals` (as well as `contains` +
`not_contains`, and `in` + `not_in`) cover the whole enabled catalog without overlap.

String comparisons on attributes read the `text` field and the `json` field, so select and
multi-select attribute values stored as JSON are matched too.

At save time:

- product links that no longer match are detached;
- newly matched products are attached;
- products that remain assigned keep their existing position.

In the admin taxon form, a preview action is available in the conditions section to test the current condition set and display:

- total number of matching products;
- sample matching products.

An additional warning in the admin form explains:

- save-time mass assignment;
- periodic automated synchronization.

## Mega menu

The mega menu is **enabled at channel level**.
That point is important because the feature is not toggled globally.

To enable it:

1. Open a channel in the Sylius back office.
2. Go to the relevant channel form section.
3. Enable the `Has mega menu` option.

Once enabled for a channel, the storefront header uses the plugin mega menu rendering for that channel.

## Tree and admin rendering

In the taxon update screen, the left tree reflects taxon visual customization:

- label color;
- icon or pictogram preview;
- 20x20 rendering for the taxon visual.

This helps editors navigate a deeply customized taxonomy without opening each taxon one by one.

## Operational notes

- Uploaded pictograms are stored under `public/media/advanced-taxon/icons` of the application.
- Advanced media files continue to use Sylius taxon media storage, with plugin-specific metadata such as title, description, URL, position, and zone.
- Uploaded taxon media can be resized automatically before persistence.
- Custom frontend logic should be implemented in Stimulus controllers under `assets/*/js` and styling under `assets/*/scss`.
