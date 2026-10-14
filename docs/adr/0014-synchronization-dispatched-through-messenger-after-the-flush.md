# 0014. The synchronization is dispatched through Messenger once the flush is over, synchronous unless routed

- Status: accepted
- Date: 2026-10-14

## Context

Saving a conditional taxon has to recompute its products. Doing it in a Doctrine `postFlush`
listener means flushing inside a flush, which Doctrine does not support: the collection deletions
of the outer flush, not cleaned up yet, run again and lose data. Doing it in a worker only would
force every shop to run one.

## Decision

The taxons to synchronize are collected during the flush; a message per taxon is dispatched on
the Sylius command bus once the flush is over, at the end of the request, of the console command
or of the message a worker handled. The handler is registered on that bus only, whose transaction
middleware flushes. With no routing, the message is handled synchronously.

## Consequences

- No dependency on a transport: a shop routes the message to an asynchronous one when saving a
  taxon must not wait for the assignments.
- Synchronously, a failing synchronization answers an error although the taxon is already saved.
- Code that flushes outside a request, a command or a worker (a plain script) leaves the
  synchronization to the command.
- The message is public contract.

## Related

- [0011. Conditional taxons are materialized as product-taxon links](0011-conditional-taxons-materialized-as-product-taxon-links.md)
- [0029. Services holding per-request memory are reset](0029-per-request-memory-services-are-reset.md)
