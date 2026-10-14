# 0032. No `CHANGELOG.md`

- Status: accepted
- Date: 2026-10-14

## Context

A changelog file repeats what the pull requests already describe, and drifts from the releases
when it is forgotten.

## Decision

The GitHub release notes, built from the pull requests, are the changelog. `UPGRADE.md` only says
what a shop has to do when it upgrades.

## Consequences

- A pull request describes its change for the release notes.
- A pull request that breaks the public contract adds its entry to `UPGRADE.md`.

## Related

- [0031. A schema change is a new migration, written against the DBAL `Schema` API](0031-schema-change-is-a-new-migration.md)
