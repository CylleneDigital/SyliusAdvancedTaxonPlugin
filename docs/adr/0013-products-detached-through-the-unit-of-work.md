# 0013. Products are detached through the unit of work, not a DQL `DELETE`

- Status: accepted
- Date: 2026-10-14

## Context

Detaching products from a conditional taxon with a bulk `DELETE` would skip the Doctrine events
(search indexers listen to them) and leave the product collections already in memory with links that
no longer exist.

## Decision

The links to remove are loaded and removed through the entity manager, by chunks. A product already
in memory has its collection updated; the others are not loaded.

## Consequences

- Listeners see each removed link, like a removal from the product form.
- Detaching costs more than a bulk delete, without loading the products themselves.

## Related

- [0012. A conditional taxon owns its product links](0012-conditional-taxon-owns-its-product-links.md)
- [0014. The synchronization is dispatched through Messenger once the flush is over, synchronous unless routed](0014-synchronization-dispatched-through-messenger-after-the-flush.md)
