# 0018. The condition preview mirrors the synchronization

- Status: accepted
- Date: 2026-10-14

## Context

The back office previews the products matching the conditions being edited. A preview that
reads another locale, or other products, than the synchronization would announce a result the
taxon will not get.

## Decision

The preview runs the synchronization query on the same products, disabled ones included, in the
default locale, whatever the locale of the back office. The request body is decoded leniently: an
invalid body previews no product instead of failing. The number of conditions read and of products
returned is capped.

## Consequences

- What the preview shows is what the next synchronization attaches.
- Rows being typed, incomplete or with a reference that does not exist, are skipped instead of
  failing the preview.

## Related

- [0015. A condition that cannot apply matches nothing](0015-non-applicable-condition-matches-nothing.md)
