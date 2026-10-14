# 0032. A workaround for an upstream bug never constrains the shops

- Status: accepted
- Date: 2026-10-14

## Context

A bug in Sylius or in a dependency can break the plugin's tests or its test application. Capping the
dependency in the plugin's requirements would impose the cap on every shop, including the ones the
bug does not affect or that fixed it their own way.

## Decision

A workaround for an upstream bug stays in the development requirements or in the CI jobs. The
plugin's own constraints stay open.

## Consequences

- The plugin never blocks a shop from upgrading a dependency.
- A shop affected by the upstream bug applies the workaround itself.
- A workaround is removed once the upstream fix is released.
