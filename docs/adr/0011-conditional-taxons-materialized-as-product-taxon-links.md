# 0011. Conditional taxons are materialized as product-taxon links

- Status: accepted
- Date: 2026-10-14

## Context

A conditional taxon gets its products from rules (attribute, option, name, description, stock,
taxon membership). Resolving the
rules when the taxon is listed would only serve the plugin listing: the API, the search, the
product forms and the other plugins read the product-taxon links Sylius stores.

## Decision

The rules are materialized: the matching products are attached to the taxon as regular Sylius
product-taxon links, and kept in step when the conditions change or on demand with a command.
Nothing is resolved when the shop reads the taxon.

## Consequences

- Every part of Sylius sees a conditional taxon as a normal one.
- A product that starts or stops matching (a new attribute value, a new product) is only attached
  or detached by the next synchronization: the command is meant to be scheduled.

## Related

- [0012. A conditional taxon owns its product links](0012-conditional-taxon-owns-its-product-links.md)
- [0014. The synchronization is dispatched through Messenger once the flush is over, synchronous unless routed](0014-synchronization-dispatched-through-messenger-after-the-flush.md)
- [0015. A condition that cannot apply matches nothing](0015-non-applicable-condition-matches-nothing.md)
