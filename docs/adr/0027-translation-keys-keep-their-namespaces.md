# 0027. Translation keys keep their namespaces

- Status: accepted
- Date: 2026-10-14

## Context

The translation keys of the plugin sit under a few namespaces (shop, form, ui, facet), while
other plugins put their labels under a single `ui` namespace. Aligning them renames keys an
application may have overridden.

## Decision

The keys keep their namespaces.

## Consequences

- An application's overrides keep working.
- Aligning the keys would be a major version.

## Related

- [0026. Help texts only where a field's effect is not obvious](0026-help-texts-only-where-the-effect-is-not-obvious.md)
