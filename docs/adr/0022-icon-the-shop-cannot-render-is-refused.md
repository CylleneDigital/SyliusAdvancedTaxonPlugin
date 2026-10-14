# 0022. An icon the shop cannot render is refused on save

- Status: accepted
- Date: 2026-10-14

## Context

Sylius configures UX Icons to ignore an icon it cannot find: a taxon icon with a typo or from a
library the shop does not have disappears without any error.

## Decision

On save, the taxon form asks the icon renderer to render a new icon and adds a form error when it
cannot.

## Consequences

- An administrator sees at once that an icon does not exist.
- An icon already stored is not checked again: one that becomes unavailable later (a library
  removed) still disappears silently.

## Related

- [0020. Values rendered in `style` or `href` are sanitized on write](0020-values-sanitized-on-write.md)
- [0021. Template icons are local](0021-template-icons-are-local.md)
