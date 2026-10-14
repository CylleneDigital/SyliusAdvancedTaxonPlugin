# 0028. Services are declared explicitly, without autowiring

- Status: accepted
- Date: 2026-10-14

## Context

Symfony recommends that a reusable bundle does not rely on autowiring nor autoconfiguration: its
services must not depend on what the host application happens to alias or tag, and autowiring
would turn every class of the plugin into an injectable service, public by accident.

## Decision

Every service is declared by hand, one file per area, with a prefixed id and every argument and
tag written out. The controller is the exception to the prefix: it keeps its class as id, since its
route names it that way. The interfaces an application may decorate are aliased to their service.

## Consequences

- A new constructor argument is added to the service definition.
- A tag autoconfiguration would have set is added by hand.
- Only the attributes read at runtime are used in the classes; the ones that only
  autoconfiguration reads would do nothing.

## Related

- [0029. Services holding per-request memory are reset](0029-per-request-memory-services-are-reset.md)
- [0030. A `Bundle` class with `SyliusPluginTrait`, not `AbstractBundle`](0030-bundle-class-with-sylius-plugin-trait.md)
