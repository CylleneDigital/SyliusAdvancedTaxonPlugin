# 0018. The condition preview mirrors the synchronization

- Status: accepted
- Date: 2026-10-14

## Context

The back office previews the products matching the conditions being edited. A preview that reads
another locale, or other products, than the synchronization would announce a result the taxon will
not get.

## Decision

The preview applies the conditions the way the synchronization does, on the same products, disabled
ones included, in the default locale, whatever the locale of the back office. The request body is
decoded leniently: an invalid body previews no product instead of failing. The number of conditions
read and of products returned is capped.

## Consequences

- Once every row is complete and valid, what the preview shows is what the next synchronization
  attaches.
- Rows being typed, incomplete or with a reference that does not exist, are skipped instead of
  failing the preview.

## Related

- [0011. Conditional taxons are materialized as product-taxon links](0011-conditional-taxons-materialized-as-product-taxon-links.md)
- [0015. A condition that cannot apply matches nothing](0015-non-applicable-condition-matches-nothing.md)
- [0016. Conditions compare case-insensitively on every database](0016-conditions-compare-case-insensitively.md)
