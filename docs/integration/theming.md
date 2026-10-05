# Theming

The plugin changes the admin and the shop **only through Twig hooks** (`sylius/twig-hooks`). It
overrides no Sylius template by file. Every change is declared in two files, loaded by
`@CylleneDigitalSyliusAdvancedTaxonPlugin/config/config.yaml`:

- `config/twig_hooks/admin.yaml`
- `config/twig_hooks/shop.yaml`

That import must come after the Sylius configuration: see
[Installation](installation.md#import-the-plugin-configuration).

Inspect what a page actually renders (Sylius 2.3: the command comes with `sylius/twig-hooks` 0.12):

```bash
bin/console sylius:debug:twig-hooks sylius_shop.product.index.content.body.sidebar --all
bin/console sylius:debug:twig-hooks sylius_shop.product.index.content.body.main --all --config
```

`--all` also lists disabled hookables. On Sylius 2.1 and 2.2, read the merged configuration instead:
`bin/console debug:config sylius_twig_hooks`.

## Vocabulary

| Term | Meaning |
|---|---|
| **Added** | New hookable name; Sylius hookables of the hook keep rendering |
| **Replaced** | Same hookable name as Sylius, with a plugin template (and sometimes another priority) |
| **Disabled** | `enabled: false` on a Sylius hookable: it no longer renders |

Template paths below are relative to `@CylleneDigitalSyliusAdvancedTaxonPlugin/` (the plugin
`templates/` directory).

## Admin hooks

### Taxon form (`create` and `update`)

Each row applies to both `sylius_admin.taxon.create.*` and `sylius_admin.taxon.update.*`.

| Hook | Hookable | Action | Plugin template | Sylius original |
|---|---|---|---|---|
| `…content.sections#left` | `side_navigation` | Added, priority 200 (above the Sylius `tree`, 0) | `admin/taxon/sections/side_navigation.html.twig` | - |
| `…content.sections#right` | `form` | Replaced: same `sylius_admin:taxon:form` component and props, other template (`method: PUT` kept on update) | `admin/taxon/sections/form_with_tabs.html.twig` | `@SyliusAdmin/taxon/sections/form.html.twig` |
| `…content.sections.form` | `general` | Disabled | - | `@SyliusAdmin/taxon/sections/form/general.html.twig` |
| `…content.sections.form` | `translations` | Disabled | - | `@SyliusAdmin/taxon/sections/form/translations.html.twig` |
| `…content.sections.form` | `images` | Disabled | - | `@SyliusAdmin/taxon/sections/form/images.html.twig` |
| `…content.sections.form` | `taxon_tab_general` | Added, 500, `configuration.active: true` | `admin/taxon/sections/form/tabs/general.html.twig` | - |
| `…content.sections.form` | `taxon_tab_display` | Added, 400 | `admin/taxon/sections/form/tabs/display.html.twig` | - |
| `…content.sections.form` | `taxon_tab_featured_items` | Added, 300 | `admin/taxon/sections/form/tabs/featured_items.html.twig` | - |
| `…content.sections.form` | `taxon_tab_advanced_medias` | Added, 200 | `admin/taxon/sections/form/tabs/advanced_medias.html.twig` | - |
| `…content.sections.form` | `taxon_tab_virtual_conditions` | Added, 100 | `admin/taxon/sections/form/tabs/virtual_conditions.html.twig` | - |
| `…content.sections.form` | `taxon_tab_universe` | Added, 90 | `admin/taxon/sections/form/tabs/universe.html.twig` | - |
| `…content.sections.form.general` | `advanced_filters_enabled`, `include_children_products` | Added, -100 and -200 (after the Sylius code, parent and enabled fields) | `admin/taxon/sections/form/field.html.twig` (`configuration.field`) | - |
| `…content.sections.side_navigation` | `general`, `display`, `featured_items`, `advanced_medias`, `virtual_conditions`, `universe` | Added, same priorities as the tabs | `admin/taxon/sections/side_navigation/tab.html.twig` (`configuration.tab`, `label`, `active`) | - |

Impact on an application or another plugin:

- `form_with_tabs.html.twig` wraps the `form` hook in a Bootstrap `.tab-content`. Each tab template
  renders one `.tab-pane` (`id="taxon-tab-…"`); `side_navigation.html.twig` renders the
  `…content.sections.side_navigation` hook, one button per tab (`data-bs-target="#taxon-tab-…"`).
- The Sylius `general`, `translations` and `images` hookables of `…content.sections.form` are
  disabled, but the `General` tab renders their sub-hooks: a hookable your application adds to
  `…content.sections.form.general` or `…form.translations` shows in that tab. `…form.images` is
  not rendered.
- The Sylius taxon image block is replaced by the `Advanced media` tab. Images stay in
  `sylius_taxon_image`.
- The form is closed with `render_rest: false` (Sylius default): a field added to the taxon form by
  a form extension must be rendered by a template, for instance a hookable of
  `…content.sections.form.general`. To add a tab, add a hookable to `…content.sections.form`
  rendering a `.tab-pane` with the `taxon-tab-<name>` id, and one to
  `…content.sections.side_navigation` rendering its button (the plugin `tab.html.twig` with
  `configuration.tab: <name>` and `label`).

The plugin back-office templates only use theme-aware Tabler classes (`bg-body`,
`bg-surface-secondary`, `bg-secondary-lt`...), no hard-coded color: they follow the light and dark
themes of the Sylius 2.3 back office (`data-bs-theme` on `<html>`). Keep to those classes in an
override.

### Channel form (`create` and `update`)

| Hook | Hookable | Action | Plugin template |
|---|---|---|---|
| `sylius_admin.channel.{create,update}.content.form.sections.look_and_feel` | `has_mega_menu` | Added, 150 (between the Sylius `menu_taxon`, 200, and `locales`, 100) | `admin/channel/form/sections/look_and_feel/has_mega_menu.html.twig` |

The `hasMegaMenu` field is added to `Sylius\Bundle\AdminBundle\Form\Type\ChannelType` by
`ChannelTypeExtension`.

## Shop hooks

### Taxon page (`sylius_shop.product.index`)

| Hook | Hookable | Action | Plugin template | Sylius original |
|---|---|---|---|---|
| `sylius_shop.product.index.content` | `breadcrumbs` | Replaced: same `sylius_shop:product:show:breadcrumbs` component, other template | `bundles/SyliusShopBundle/product/index/content/breadcrumbs.html.twig` | `@SyliusShop/product/index/content/breadcrumbs.html.twig` |
| `sylius_shop.product.index.content.body` | `advanced_taxon_media_top` | Added, 200: `top` media zone, full width | `shop/product/index/content/body/advanced_taxon_media_top.html.twig` (in the `sylius_shop:product:show:header` component) | - |
| `sylius_shop.product.index.content.body` | `main` | Replaced, priority 0 → 100: main column, or the full-width universe page | `shop/product/index/content/body/main_column.html.twig` | `@SyliusShop/product/index/content/body/main.html.twig` |
| `sylius_shop.product.index.content.body` | `sidebar` | Replaced, priority 100 → 0: sidebar column, not rendered for a universe | `shop/product/index/content/body/sidebar.html.twig` | `@SyliusShop/product/index/content/body/sidebar.html.twig` |
| `sylius_shop.product.index.content.body.main` | `advanced_taxon_featured_products_before_filters` | Added, 250 (between `header`, 300, and `filters`, 200), `configuration.position: before_filters` | `shop/product/index/content/body/main/featured_products.html.twig` | - |
| `sylius_shop.product.index.content.body.main` | `advanced_taxon_featured_products_after_filters` | Added, 150 (between `filters` and `products`), `configuration.position: after_filters` | `shop/product/index/content/body/main/featured_products.html.twig` | - |
| `sylius_shop.product.index.content.body.main` | `products` | Replaced: grid or list view (`view` query parameter), media of the `right` zone inserted as product cards | `shop/product/index/content/body/main/products.html.twig` | `@SyliusShop/product/index/content/body/main/products.html.twig` |
| `sylius_shop.product.index.content.body.main` | `pagination` | Replaced | `shop/product/index/content/body/main/pagination.html.twig` | `@SyliusShop/product/index/content/body/main/pagination.html.twig` |
| `sylius_shop.product.index.content.body.main` | `advanced_taxon_media_bottom` | Added, -100: `bottom` media zone | `shop/product/index/content/body/main/bottom_media.html.twig` | - |
| `sylius_shop.product.index.content.body.sidebar` | `taxonomy` | Disabled | - | `sylius_shop:product:show:taxonomy` component, `@SyliusShop/product/index/content/body/sidebar/taxonomy.html.twig` |
| `sylius_shop.product.index.content.body.sidebar` | `advanced_taxon_taxonomy` | Added, 0: facet filters when the taxon has advanced filters enabled, otherwise the Sylius `taxonomy.html.twig` (enabled child taxon links and **Go level up**) | `shop/product/index/content/body/sidebar/facet_filters.html.twig` | - |
| `sylius_shop.product.index.content.body.sidebar` | `advanced_taxon_media_left` | Added, -100: `left` media zone | `shop/product/index/content/body/sidebar/advanced_taxon_media_left.html.twig` (in the `sylius_shop:product:show:header` component) | - |
| `sylius_shop.product.index.content.body.main.header` | `image` | Replaced, priority 300 → 200: only an image whose type is empty or `main` (zone media are skipped) | `bundles/SyliusShopBundle/product/index/content/body/main/header/image.html.twig` | `@SyliusShop/product/index/content/body/main/header/image.html.twig` |
| `sylius_shop.product.index.content.body.main.header` | `featured_children` | Added, -100 (after the name and the description): featured child taxon cards | `shop/product/index/content/body/main/header/featured_children.html.twig` | - |
| `sylius_shop.product.index.content.body.main.header` | `name` | Replaced: name with the taxon icon and color | `bundles/SyliusShopBundle/product/index/content/body/main/header/name.html.twig` | `@SyliusShop/product/index/content/body/main/header/name.html.twig` |
| `sylius_shop.product.index.content.body.main.filters` | `all_filters` | Added, 150: `All filters` button, only when advanced filters are enabled | `shop/product/index/content/body/main/filters/controls/all_filters.html.twig` | - |
| `sylius_shop.product.index.content.body.main.filters` | `search` | Replaced: the Sylius search form, sending back the other query parameters of the listing (advanced filters, `sorting`, `limit`, `view`) and going back to the first page | `shop/product/index/content/body/main/filters/search.html.twig` | `@SyliusShop/product/index/content/body/main/filters/search.html.twig` |
| `sylius_shop.product.index.content.body.main.filters.search` | `clear` | Replaced: clears the search only, the other query parameters stay | `shop/product/index/content/body/main/filters/search/clear.html.twig` | `@SyliusShop/product/index/content/body/main/filters/search/clear.html.twig` |
| `sylius_shop.product.index.content.body.main.filters.controls` | `view_mode` | Added, -100: grid / list switch (`view` query parameter) | `shop/product/index/content/body/main/filters/controls/view_mode.html.twig` | - |

Kept from Sylius: `content.body.main.header` (`header` component), the `controls` of
`content.body.main.filters`, the `filter` and `submit` of its search, the `description` of the header,
the `limit` and `sort` controls.

Impact for a theme:

- **Column order.** Sylius renders the sidebar first. The plugin renders the main column first and
  places the sidebar on the left on large screens with `order-lg-1` / `order-lg-2`
  (`col-lg-3` / `col-lg-9`). On small screens, the main column comes first.
- **Sidebar category list.** The Sylius `taxonomy` hookable is disabled on every taxon.
  `advanced_taxon_taxonomy` shows the facet filters on taxons with advanced filters, and otherwise
  reproduces the child links and **Go level up**; the `left` media zone follows. Re-enable the
  Sylius hookable as shown [below](#re-enable-a-sylius-hookable).
- **Universe taxons.** `main_column.html.twig` renders
  `shop/product/index/content/body/main/universe.html.twig` in a `col-12` instead of opening the
  `main` hook; `sidebar.html.twig` renders nothing. Hookables added to
  `sylius_shop.product.index.content.body.main` (by you or by Sylius) do not render on a universe
  page.
- **Ids used by the scripts.** `#at-advanced-filters-main-column`,
  `#at-advanced-filters-sidebar-column` and `#at-advanced-filters-panel` are read by the
  `at-advanced-filters` controller. Keep them in an override.

### Header and menu (`sylius_shop.base.header`)

| Hook | Hookable | Action | Plugin template | Sylius original |
|---|---|---|---|---|
| `sylius_shop.base.header.content` | `taxon_hamburger` | Replaced: with a mega menu, the burger (`#at-mn-burger`) opens the drill-down drawer; otherwise the Bootstrap offcanvas, as Sylius | `bundles/SyliusShopBundle/shared/layout/base/header/content/taxon_hamburger.html.twig` | `@SyliusShop/shared/layout/base/header/content/taxon_hamburger.html.twig` |
| `sylius_shop.base.header.navbar` | `menu` | Replaced: same `sylius_shop:common:taxon_menu` component, other template. Mega menu when the channel enables it, otherwise the Sylius template itself (`@!SyliusShop`), which opens the `menu` hook | `bundles/SyliusShopBundle/shared/layout/base/header/navbar/menu.html.twig` | `@SyliusShop/shared/layout/base/header/navbar/menu.html.twig` |
| `sylius_shop.base.header.navbar.menu.item#link` | `link` | Replaced: taxon label with icon and color | `bundles/SyliusShopBundle/shared/layout/base/header/navbar/menu/item/link.html.twig` | `@SyliusShop/shared/layout/base/header/navbar/menu/item/link.html.twig` |
| `sylius_shop.base.header.navbar.menu.item#dropdown` | `toggle` | Replaced | `bundles/SyliusShopBundle/shared/layout/base/header/navbar/menu/item/toggle.html.twig` | `@SyliusShop/shared/layout/base/header/navbar/menu/item/toggle.html.twig` |
| `sylius_shop.base.header.navbar.menu.item#dropdown` | `dropdown` | Replaced | `bundles/SyliusShopBundle/shared/layout/base/header/navbar/menu/item/dropdown.html.twig` | `@SyliusShop/shared/layout/base/header/navbar/menu/item/dropdown.html.twig` |

In mega menu mode, the menu is a self-contained structure (`.at-mn*` classes, styled by
`assets/shop/scss/mega-menu.scss`). It opens none of the Sylius `sylius_shop.base.header.navbar.menu.*`
hooks: hookables you add there only render when the mega menu is off.

### Templates under `templates/bundles/SyliusShopBundle/`

The plugin keeps some templates under `templates/bundles/SyliusShopBundle/`. They are **not**
Symfony bundle overrides: Symfony only reads `templates/bundles/` of the **application**. They are
referenced explicitly from `config/twig_hooks/shop.yaml`, as
`@CylleneDigitalSyliusAdvancedTaxonPlugin/bundles/SyliusShopBundle/…`. Disabling or redefining the
hookable is enough to stop using them.

## Override a plugin template

Two ways, from the application.

**Same path, Symfony convention.** Copy the plugin template to
`templates/bundles/CylleneDigitalSyliusAdvancedTaxonPlugin/<same path>`. It replaces the template
everywhere it is rendered or included.

```
templates/bundles/CylleneDigitalSyliusAdvancedTaxonPlugin/shop/product/index/content/body/main/filters/controls/view_mode.html.twig
templates/bundles/CylleneDigitalSyliusAdvancedTaxonPlugin/bundles/SyliusShopBundle/product/index/content/body/main/header/name.html.twig
templates/bundles/CylleneDigitalSyliusAdvancedTaxonPlugin/components/advanced_taxon/taxon_label.html.twig
```

**Point the hookable to your template.** In a configuration file loaded after the plugin one:

```yaml
# config/packages/zz_advanced_taxon_theme.yaml
sylius_twig_hooks:
    hooks:
        'sylius_shop.product.index.content.body.main.filters.controls':
            view_mode:
                template: 'shop/taxon/view_mode.html.twig'
```

Templates read the taxon from `hookable_metadata.context.taxon`, or resolve it from the request slug
with `advanced_taxon_taxon_from_request()`. The Twig functions are listed in
[Twig functions](../shop/twig-functions.md).

Overridden templates are not covered by the backward compatibility promise: see
[Public contract](../architecture/public-contract.md).

## Disable a plugin hookable

```yaml
# config/packages/zz_advanced_taxon_theme.yaml
sylius_twig_hooks:
    hooks:
        'sylius_shop.product.index.content.body.main.filters.controls':
            view_mode:
                enabled: false
        'sylius_shop.product.index.content.body.main.header':
            featured_children:
                enabled: false
```

The file must be loaded after the plugin configuration (here, `zz_` sorts after
`cyllene_digital_sylius_advanced_taxon.yaml`).

## Re-enable a Sylius hookable

Give the full Sylius definition back, so the result does not depend on how the definitions merge:

```yaml
# config/packages/zz_advanced_taxon_theme.yaml
sylius_twig_hooks:
    hooks:
        # Sylius sub-taxon list in the sidebar
        'sylius_shop.product.index.content.body.sidebar':
            taxonomy:
                enabled: true
                component: 'sylius_shop:product:show:taxonomy'
                props:
                    template: '@SyliusShop/product/index/content/body/sidebar/taxonomy.html.twig'
                priority: 0

        # Sylius menu, without the mega menu nor the plugin taxon labels
        'sylius_shop.base.header.content':
            taxon_hamburger:
                template: '@SyliusShop/shared/layout/base/header/content/taxon_hamburger.html.twig'
                priority: 0
        'sylius_shop.base.header.navbar':
            menu:
                component: 'sylius_shop:common:taxon_menu'
                props:
                    template: '@SyliusShop/shared/layout/base/header/navbar/menu.html.twig'
                priority: 0
        'sylius_shop.base.header.navbar.menu.item#link':
            link:
                template: '@SyliusShop/shared/layout/base/header/navbar/menu/item/link.html.twig'
                priority: 0
        'sylius_shop.base.header.navbar.menu.item#dropdown':
            toggle:
                template: '@SyliusShop/shared/layout/base/header/navbar/menu/item/toggle.html.twig'
                priority: 100
            dropdown:
                template: '@SyliusShop/shared/layout/base/header/navbar/menu/item/dropdown.html.twig'
                priority: 0
```

The Sylius definitions are in `vendor/sylius/sylius/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/`
and `vendor/sylius/sylius/src/Sylius/Bundle/AdminBundle/Resources/config/app/twig_hooks/`.

Restoring the Sylius taxon form (`general`, `translations`, `images`, and the `form` template)
removes access to every plugin field: do it only to remove the plugin.

## Disable a feature

| Feature | How |
|---|---|
| Mega menu | Per channel: `Enable mega menu` off. The plugin menu templates stay (taxon icon and color in the standard menu) |
| Taxon icon and color in the menu, breadcrumbs, taxon title | Per taxon: the three "show customization" toggles (`show_customization_in_menu`, `show_customization_in_breadcrumbs`, `show_customization_on_taxon_page`) |
| Advanced filters | Per taxon: `Enable advanced filters in the shop` (off by default) |
| Children products in the listing | Per taxon: `Include products from child taxons` (off by default), unless `sylius_shop.product_grid.include_all_descendants` is `true` |
| Universe page | Per taxon: `This taxon is a universe` (off by default) |
| Featured products | Per taxon: `Enable featured products display` (off by default) |
| Grid / list switch | Hook: disable `view_mode` |
| Whole plugin menu | Hooks: restore the Sylius `menu`, `link`, `toggle`, `dropdown` and `taxon_hamburger` hookables |
| Media zones | Hooks: disable `advanced_taxon_media_top`, `advanced_taxon_media_left`, `advanced_taxon_media_bottom`; the `right` zone (product cards) is rendered by `products` |

## `taxon_label` partial

`components/advanced_taxon/taxon_label.html.twig` renders a taxon name with its icon (glyph or
uploaded pictogram) and color. It is a plain template, included with `include … only`, not a Twig
component. The plugin uses it in the breadcrumbs, the taxon title and the menu.

```twig
{% include '@CylleneDigitalSyliusAdvancedTaxonPlugin/components/advanced_taxon/taxon_label.html.twig' with {
    taxon: taxon,
    iconSize: 20,
    wrapperClass: 'd-inline-flex align-items-center gap-2',
    showCustomization: taxon.showCustomizationInMenu
} only %}
```

| Variable | Default | Effect |
|---|---|---|
| `taxon` | required | An `AdvancedTaxonInterface` |
| `iconSize` | `30` | Icon width and height, in pixels |
| `wrapperClass` | `'d-inline-flex align-items-center gap-2'` | Class of the outer `<span>` |
| `textClass` | `''` | Class of the name `<span>` |
| `textColor` | `null` | Forces the name color |
| `iconColor` | `null` | Forces the glyph color |
| `useTaxonColor` | `true` | Applies the taxon color to the name and the glyph |
| `showCustomization` | `true` | `false` renders the name only, without icon nor color |

A glyph without prefix is rendered as `tabler:<name>`. A pictogram is rendered through the
`cyllene_advanced_taxon_icon` filter set.

## Styles

The plugin classes are prefixed `at-` (`.at-mn-*` for the mega menu). Its styles come from the
entrypoints described in [Assets](assets.md). Override them in your own SCSS, loaded after the
plugin entrypoint import.
