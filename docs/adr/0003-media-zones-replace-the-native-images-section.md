# 0003. Plugin media zones replace the native taxon Images section

- Status: accepted
- Date: 2026-10-14

## Context

The plugin places taxon images in zones (main image, top, bottom, left, inside the product list,
featured, universe slider), each with its own settings, stored as Sylius taxon images with a type.
Keeping the native Images collection next to the zones would have two forms managing the same
images, and the native collection would delete the zone media when the taxon is saved. Showing
the native collection only for the types the plugin does not use would still give two image
interfaces in one form.

## Decision

The native Images section is removed from the taxon form and replaced by the zones. A main image
zone takes over the images Sylius stores without type; they get the type of that zone when the
taxon is saved, and the shop keeps showing the first one as the taxon image.

## Consequences

- Images of other types, set by a theme or another plugin, are kept in the database but can no
  longer be edited from the taxon form.
- One image interface in the taxon form, with one uploader and one storage: the Sylius ones.

## Related

- [0019. Pictograms are Sylius images](0019-pictograms-are-sylius-images.md)
- [0023. The random media display modes are kept](0023-random-media-display-modes-kept.md)
