# Features

The complete list of what the plugin does, grouped by area. Each line links to the page that details
it. Labels in **bold** are the exact English labels shown in the back office.

Everything is configured per taxon, except the mega menu, which is switched on per channel. There is
no per-channel taxon setting.

## Back office: taxon form

The native taxon form is replaced by a form split into six tabs, listed in a side navigation
([guide](admin/user-guide.md#taxon-form)). The native Sylius **Images** section is replaced
by the plugin media zones, the main image included. Fields whose behavior is not obvious carry a
one-line help text. The
plugin back-office screens follow the light and dark themes of the Sylius 2.3 back office.

| Tab | Settings | Details |
| --- | --- | --- |
| **General** | native code, parent, enabled, translations; **Enable advanced filters in the shop**; **Include products from child taxons** | [General](admin/user-guide.md#general) |
| **Advanced customizations** | **Use a color** and **Color**, **Icon type**, **Icon** (with an icon picker), **Pictogram file**, three visibility toggles, **Apply a colored background behind the universe page title** | [Advanced customizations](admin/user-guide.md#advanced-customizations) |
| **Featured elements** | **Featured child taxons**, **Featured products**, **Enable featured products display**, **Featured products position**, **Display featured products as a slider**, block titles and descriptions per locale | [Featured elements](admin/user-guide.md#featured-elements) |
| **Advanced media** | six media zones, each with a media collection and, except the main image, a display mode | [Advanced media](admin/user-guide.md#advanced-media) |
| **Mass-assignment conditions** | read-only conditional status, condition rows, **Add a condition**, **Test conditions** | [Mass-assignment conditions](admin/user-guide.md#mass-assignment-conditions) |
| **Universe** | **This taxon is a universe**, **Use the taxon color as universe page background**, universe slider media, universe texts per locale | [Universe](admin/user-guide.md#universe) |

- Icon picker: a modal lists the Tabler icons shipped with Sylius UI plus any library declared in
  the configuration; clicking an icon fills the **Icon** field
  ([guide](admin/user-guide.md#advanced-customizations), [configuration](integration/configuration.md)).
- Featured product choices are restricted to the products assigned to the taxon, and only once the
  taxon exists; featured child choices are restricted to its direct children, and only when it has
  some ([guide](admin/user-guide.md#featured-elements)).
- Media positions are pre-filled: a new media gets the highest position of its zone plus one
  ([guide](admin/user-guide.md#media-fields)).

## Back office: channel form

- **Enable mega menu** (section *Look & feel*, off by default): replaces the shop main menu of that
  channel with the mega menu and its mobile drawer ([guide](admin/user-guide.md#channel-form),
  [storefront](shop/storefront.md#mega-menu)).

## Featured content

- **Featured products**: a hand-picked product block on the taxon page, as a grid or a horizontal
  slider, placed before or after the native filters, with a title and description per locale
  ([guide](admin/user-guide.md#featured-elements), [storefront](shop/storefront.md#featured-products)).
  Only enabled products available in the current channel are shown.
- **Featured child taxons**: a card block on the taxon page, moved first in every menu, and shown in
  a dedicated colored column of the mega menu ([storefront](shop/storefront.md#featured-child-taxons),
  [menus](shop/storefront.md#navigation-menu)). Only enabled children are shown.
- On a universe page, the featured products of each child taxon that has **Enable featured products
  display** on are shown as one slider per child ([storefront](shop/storefront.md#universe-page)).

## Media zones

Media are Sylius taxon images with a zone stored in their `type`, a position, an optional link URL,
and a title and description per locale ([guide](admin/user-guide.md#advanced-media),
[schema](persistence/schema.md)).

| Zone (admin title) | Stored `type` | Display mode (default) | Where it shows |
| --- | --- | --- | --- |
| **Main image** | `main` (or empty) | none, lowest position | taxon page header, above the name ([details](shop/storefront.md#header)) |
| **Top zone** | `top` | **Stacked** / **Random** / **Slider** (`stacked`) | full width, above the listing and the sidebar ([details](shop/storefront.md#media-zones)) |
| **Bottom zone** | `bottom` | **Stacked** / **Random** / **Slider** (`stacked`) | under the pagination; also at the bottom of a universe page |
| **Left zone** | `left` | **Stacked** / **Random** (`stacked`) | sidebar, under the filters or the child links |
| **Display as product card** | `right` | beginning / end / random middle of the product list (`products_end`) | as cards inside the product listing ([details](shop/storefront.md#media-cards-in-the-listing)) |
| **Spotlight zone** | `featured` | **Stacked** / **Random** (`stacked`) | one image: mega menu hover image, child taxon cards |
| **Universe slider** (Universe tab) | `slider_universe` | none, always a slider | hero slider of the universe page ([details](shop/storefront.md#hero-slider)) |

- **Show title and description in the card**: per media, only in the product card zone, in the
  grid and the list views ([guide](admin/user-guide.md#media-fields)).
- The link URL is only offered, and used, by product cards and universe slides
  ([guide](admin/user-guide.md#media-fields)).
- Uploaded media larger than 1600 px or 2 MB are downscaled before storage, keeping their format
  ([guide](admin/user-guide.md#image-processing)).

## Taxon page (storefront)

- Listing through the native Sylius shop product grid, extended with children products and advanced
  filters; pagination, sorting, search and channel restriction stay native
  ([storefront](shop/storefront.md#product-listing), [contract](architecture/public-contract.md)).
- **Include products from child taxons**: the listing also shows the products of descendant taxons
  ([storefront](shop/storefront.md#product-listing)).
- Grid / list switch next to the sorting controls, kept in the `view` URL parameter
  ([storefront](shop/storefront.md#grid-and-list-views)).
- Header: main taxon image, taxon name with its color and icon, then the featured child taxons
  ([storefront](shop/storefront.md#header)).
- Breadcrumbs with the color and icon of each taxon ([storefront](shop/storefront.md#breadcrumbs)).
- Sidebar: advanced filters when enabled, otherwise the child taxon links and **Go level up**; then
  the left zone media ([storefront](shop/storefront.md#sidebar)).

## Advanced filters

Enabled per taxon with **Enable advanced filters in the shop**
([guide](admin/user-guide.md#general), [storefront](shop/storefront.md#advanced-filters)).

- Facets: price (min / max), **Sub-taxons**, product attributes (text and select values), product
  options. Several values can be checked in a facet (OR inside a facet, AND between facets).
- Each value shows its product count, computed on the same products as the listing (channel,
  enabled products, children option, search) with the other selected filters applied. The native
  search is applied with the Sylius semantics (`LIKE` on the product name: case-sensitive on
  PostgreSQL).
- A search field in every facet narrows its values as you type, ignoring case and accents; long
  lists scroll.
- Prices are read from the channel pricings of the current channel.
- Filters travel in the `advanced[...]` URL parameter and apply to the paginated grid
  ([URL parameters](shop/storefront.md#url-parameters)).
- The native search and its clear button keep the selected filters, the sorting, the page size and
  the view.
- An **All filters** button shows or hides the panel on desktop and opens it as a drawer on mobile.

## Conditional taxons

([guide](admin/user-guide.md#mass-assignment-conditions), [domain](domain/conditional-taxons.md))

- Condition types: **Product attribute**, **Product option**, **Product name**, **Product
  description**, **Stock availability**, **Taxon membership**; operators per type (equals, not
  equal, contains, does not contain, in, not in, is in stock). All conditions are combined with AND.
- Negative operators exclude every product owning a matching value.
- **Test conditions**: a live preview of the matching count and up to 20 products, before saving,
  counted like the synchronization ([guide](admin/user-guide.md#testing-conditions)).
- Materialization: matching products are attached to the taxon as regular product/taxon links when
  the conditions change, through a Messenger message (synchronous unless routed); positions of
  products that stay are kept, products that stop matching are detached.
- The console command `cyllene:advanced-taxon:sync-conditional-taxons` resynchronizes every
  conditional taxon; it has to be scheduled by the application
  ([installation](integration/installation.md#6-keep-conditional-taxons-in-sync)).
- The conditional flag follows the conditions, whatever wrote them (admin, API, fixtures, import).
- A stored condition that is incomplete, or whose attribute, option or taxon does not exist, makes
  the taxon match no product, negative operators included (fail closed).

## Universe pages

**This taxon is a universe** turns the taxon page into a full-width showcase page without product
listing, sidebar, filters or pagination ([guide](admin/user-guide.md#universe),
[storefront](shop/storefront.md#universe-page)). In order:

1. hero slider from the universe slider media, with optional link per slide, a lighter mobile image
   and a 5-second autoplay;
2. intro card: universe page title (taxon name by default) and description, optionally on a
   background of the taxon color;
3. one card per enabled child taxon (featured children first), with the child color, its spotlight
   image and a hover panel listing its own children;
4. an optional title and description, then one featured products slider per child taxon that has
   **Enable featured products display** on and visible featured products, each with a **View
   category** button;
5. bottom zone media.

The whole page can use the taxon color as a background gradient.

## Navigation

([storefront](shop/storefront.md#navigation-menu))

- Standard menu (default): native Sylius menu with nested dropdowns at any depth; featured children
  are listed first at each level ([details](shop/storefront.md#standard-menu)).
- Mega menu (per channel): full-width hover panels with a colored **Featured categories** column
  (taxon color), level 2 columns with up to five level 3 links and a **View category** link, and the
  spotlight image of the hovered taxon ([details](shop/storefront.md#mega-menu)).
- Mobile menu in mega menu mode: a drill-down drawer over three levels with **Back**, **View all
  products** and close controls ([details](shop/storefront.md#mobile-menu)).
- Menu queries are preloaded in grouped queries for the first three levels.

## Taxon label

A taxon name rendered with its color (text and glyph) and its icon or pictogram
([storefront](shop/storefront.md#taxon-labels), [guide](admin/user-guide.md#advanced-customizations)):

| Toggle (default on) | Applies to |
| --- | --- |
| **Show color and icon customization in main menu and mega menu** | standard menu, mega menu, mega menu panel background |
| **Show color and icon customization on taxon page** | taxon page title |
| **Show color and icon customization in breadcrumbs** | breadcrumb items |

The same label is available to themes ([theming](integration/theming.md),
[Twig functions](shop/twig-functions.md)).

## Validation and safety

([guide](admin/user-guide.md#validation-messages))

- Color: only `#rrggbb` values are stored; anything else is discarded. In the admin, the color is
  only kept when **Use a color** is checked (a color input always submits a value, `#000000` when
  untouched).
- Icon: only a glyph name (`prefix:name` or `name`) or an uploaded pictogram reference is stored; a
  glyph the shop cannot find in its icon libraries is refused on save.
- Pictogram: PNG, JPEG, WebP or GIF, 2 MB at most, checked on the file content (SVG refused); stored
  in the Sylius image storage; the replaced pictogram, or the pictogram of a deleted taxon, is deleted.
- Media link URL: only `http(s)://` URLs and same-site paths starting with `/` are stored, and the
  URL is checked again when rendered on product cards and universe slides.
- Media display modes of the top, bottom, left and spotlight zones: `stacked` or `random`, and
  `slider` for the top and bottom zones; any other value is stored as `stacked`.
- Media files: Sylius taxon image constraints (image, 10 MB, allowed MIME types).
- Conditions: valid type, operator allowed for the type, reference and value required where
  needed, existing attribute, option or taxon, a taxon never referencing itself, 255 characters at
  most.
- A universe cannot be the main taxon of a product.
- The condition preview endpoint requires admin access and a CSRF token.

## Configuration

- `icon_libraries`: extra icon sets for the picker, on top of the auto-discovered Tabler icons,
  each with a required `icon_prefix` ([configuration](integration/configuration.md)).
- Liip Imagine filter sets `cyllene_advanced_taxon_icon` (pictograms, 160 x 160) and
  `cyllene_universe_slider_mobile` (640 x 360), overridable
  ([configuration](integration/configuration.md)).
- Messenger routing of `SynchronizeConditionalTaxon`
  ([configuration](integration/configuration.md#conditional-taxon-synchronization-messenger)) and
  scheduling of the sync command ([installation](integration/installation.md#6-keep-conditional-taxons-in-sync)).
- Installation, entity traits and assets: [installation](integration/installation.md),
  [assets](integration/assets.md).

## What this plugin does not do

- It does not replace the Sylius models: your `Taxon`, `TaxonImage` and `Channel` entities must use
  the plugin interfaces and traits ([installation](integration/installation.md)).
- The native **Images** section of the taxon form is gone: the main image is edited in the **Main
  image** zone, which also lists the Sylius images without type. Images of any other type (a type of
  your own theme) stay in the database and on the pages that use them, but cannot be edited from
  the taxon form any more.
- No search engine or index: facets, counts and conditions are SQL queries run on each request or
  synchronization.
- Facets cannot be configured: every attribute and option carried by the listed products appears.
  Attribute facets and attribute conditions only read text values (text, textarea) and select
  values; integer, float, percent, boolean and date attributes are ignored (the condition editor
  does not offer them).
- No OR between conditions, and **Taxon membership** only matches products assigned to that exact
  taxon, not to its descendants. Conditions are not channel-scoped.
- Conditional taxons are not dynamic: a product that starts matching after the last save is only
  attached by the next condition change or by the sync command, which the plugin does not schedule.
- The synchronization owns the product links of a conditional taxon: products added by hand that do
  not match are detached, and removing every condition detaches every product of the taxon.
- No per-channel taxon settings, no API Platform resources or serialization for the new fields, no
  fixtures.
- The mega menu shows three levels (five level 3 links per column); the mobile drawer three levels.
- The product page is not changed.
