# 0019. Pictograms are Sylius images

- Status: accepted
- Date: 2026-10-14

## Context

A taxon can show an uploaded pictogram instead of a glyph. Storing the file in the public
directory breaks on several servers, on a read-only container, and on a shop whose images are in
object storage.

## Decision

A pictogram is stored through the Sylius image uploader and storage, and resized by a Liip
Imagine filter the plugin prepends (an application can override it). The icon field holds an
`image:` reference. The file is removed once the change that drops it is flushed; the previous
value is read from the database, never from the submitted form.

## Consequences

- Pictograms follow the shop's image storage, wherever it is.
- A failed flush leaves the file in place; a forged form cannot get another file deleted.

## Related

- [0003. Plugin media zones replace the native taxon Images section](0003-media-zones-replace-the-native-images-section.md)
- [0020. Values rendered in `style` or `href` are sanitized on write](0020-values-sanitized-on-write.md)
