# 0025. Twig functions are served by lazy runtimes

- Status: accepted
- Date: 2026-10-14

## Context

The plugin declares many Twig functions, most of them used by a single kind of page. Declaring
them on extensions that hold their dependencies builds every dependency on every page.

## Decision

One Twig extension declares the functions; each is served by a runtime, built on the first call
of one of its functions.

## Consequences

- A page only builds what it uses.
- The function names are public contract, the runtimes are internal.

## Related

- [0010. Featured items are filtered for visibility at render time](0010-featured-items-filtered-at-render-time.md)
- [0028. Services are declared explicitly, without autowiring](0028-explicit-services-no-autowiring.md)
