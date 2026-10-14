# 0021. Template icons are local

- Status: accepted
- Date: 2026-10-14

## Context

Symfony UX Icons fetches from Iconify, at render time, any icon its local sets do not have. A
template icon outside the few Tabler icons Sylius ships would then depend on the network, and on
`ux:icons:lock` to work offline. Mixing icon libraries also mixes licences and styles.

## Decision

Templates only use Tabler icons: the ones Sylius ships, or a Tabler SVG added to the plugin's own
set under a prefix of its own. The plugin set does not reuse a library prefix: a local set named
after a library would silently hide that library for the whole shop.

## Consequences

- No network access to render a plugin template, one licence (MIT), a style consistent with the
  Sylius admin.
- A new template icon may need a new SVG in the plugin set.

## Related

- [0022. An icon the shop cannot render is refused on save](0022-icon-the-shop-cannot-render-is-refused.md)
