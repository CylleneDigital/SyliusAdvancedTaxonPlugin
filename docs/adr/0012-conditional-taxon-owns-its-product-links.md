# 0012. A conditional taxon owns its product links

- Status: accepted
- Date: 2026-10-14

## Context

Products can be attached to a taxon by hand before it becomes conditional, or after. Keeping them as
manual assignments next to the materialized ones would make the content of a conditional taxon
depend on its history, not on its rules.

## Decision

The synchronization alone manages the links of a conditional taxon: products attached by hand that
do not match are detached. Removing the last condition, or turning the taxon back into a regular
one, detaches all its products.

## Consequences

- What a conditional taxon lists is what its conditions select, nothing else.
- Turning a taxon conditional, then back, loses its manual assignments.

## Related

- [0011. Conditional taxons are materialized as product-taxon links](0011-conditional-taxons-materialized-as-product-taxon-links.md)
- [0013. Products are detached through the unit of work, not a DQL `DELETE`](0013-products-detached-through-the-unit-of-work.md)
