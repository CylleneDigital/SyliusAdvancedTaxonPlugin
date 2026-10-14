# 0020. Values rendered in `style` or `href` are sanitized on write

- Status: accepted
- Date: 2026-10-14

## Context

The color of a taxon is rendered in a `style` attribute, the icon in markup, a media link in an
`href`. Escaping alone does not stop a `javascript:` link or a value that closes the CSS
declaration.

## Decision

The setters sanitize these values: a color that is not a hexadecimal color, an icon that is not
an icon name or an image reference, a link that is neither an HTTP(S) URL nor a same-origin path
are dropped. Media links are sanitized
again when read. The media display mode setters fall back to their default on an unknown value.

## Consequences

- Whatever writes the entity (back office, API, import, fixtures) cannot store a dangerous value.
- An invalid value is lost silently instead of raising an error.

## Related

- [0019. Pictograms are Sylius images](0019-pictograms-are-sylius-images.md)
- [0022. An icon the shop cannot render is refused on save](0022-icon-the-shop-cannot-render-is-refused.md)
