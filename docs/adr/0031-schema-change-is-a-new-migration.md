# 0031. A schema change is a new migration, written against the DBAL `Schema` API

- Status: accepted
- Date: 2026-10-14

## Context

The plugin adds columns to Sylius tables and owns tables of its own; shops run it on MySQL, MariaDB
or PostgreSQL. A migration written in SQL would target one of them. A published migration that is
edited never runs again on the shops that already executed it.

## Decision

The schema changes through migrations of the plugin, written against the DBAL `Schema` API and
registered through Sylius, which lets an application turn their prepending off. A released migration
is never edited: a change is a new migration.

## Consequences

- One migration source for the three databases.
- A shop that already ran a migration gets a schema change from the next one.
- The schema is public contract.

## Related

- [0001. The plugin never replaces a Sylius model](0001-never-replace-a-sylius-model.md)
