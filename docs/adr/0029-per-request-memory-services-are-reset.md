# 0029. Services holding per-request memory are reset

- Status: accepted
- Date: 2026-10-14

## Context

Some services keep memory for the duration of a request: resolved attribute values, checked
references, visibility lookups, taxons waiting for a synchronization. In a long-running process
(FrankenPHP worker mode, Messenger workers) that memory would leak from one request or message to
the next.

## Decision

Services are `final` and stateless where they can be. Those that hold per-request memory
implement `ResetInterface`, and get the `kernel.reset` tag in their definition.

## Consequences

- A worker never serves stale data from a previous request.
- A new service that keeps memory needs both the interface and the tag: without autoconfiguration,
  the interface alone does nothing.

## Related

- [0014. The synchronization is dispatched through Messenger once the flush is over, synchronous unless routed](0014-synchronization-dispatched-through-messenger-after-the-flush.md)
- [0028. Services are declared explicitly, without autowiring](0028-explicit-services-no-autowiring.md)
