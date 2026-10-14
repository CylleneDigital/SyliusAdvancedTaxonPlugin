# 0001. The plugin never replaces a Sylius model

- Status: accepted
- Date: 2026-10-14

## Context

A plugin that adds fields to taxons, channels and taxon images could ship its own entities and ask
the application to use them. The application then loses its own models, and two plugins doing the
same cannot be installed together. A relation that names a concrete class also breaks as soon as the
application overrides that class.

## Decision

The application keeps its own taxon, channel and taxon image entities. The plugin ships a trait and
an interface for each, with the mapping as attributes in the trait, the usual Sylius 2 pattern: an
XML mapping to copy into the application would be heavier to install. Relations target the Sylius
interfaces, resolved by Doctrine to the application's classes. The code tests interfaces, never a
Sylius or application model class. What the plugin owns alone lives in tables of its own.

## Consequences

- Installing the plugin means editing three application entities, which the installation guide and
  the Flex recipe describe.
- A taxon that does not implement the plugin interface is ignored by the shop helpers instead of
  failing.
- The traits and interfaces are public contract: changing them is a major version.

## Related

- [0031. A schema change is a new migration, written against the DBAL `Schema` API](0031-schema-change-is-a-new-migration.md)
