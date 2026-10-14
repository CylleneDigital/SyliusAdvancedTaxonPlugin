# 0032. The plugin does not cap a dependency for an upstream bug

- Status: accepted
- Date: 2026-10-14

## Context

A bug in Sylius or in a dependency can affect some of their versions. Capping the dependency in the
plugin's requirements would impose the cap on every shop, including the ones the bug does not affect
or that work around it their own way.

## Decision

The plugin's requirements stay open to every version it supports: it does not cap a dependency for
a bug that is not its own.

## Consequences

- The plugin never blocks a shop from upgrading a dependency.
- A shop affected by the upstream bug applies the workaround itself, in its own requirements.
