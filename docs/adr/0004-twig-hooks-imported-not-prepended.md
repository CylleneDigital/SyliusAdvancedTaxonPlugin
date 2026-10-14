# 0004. Twig hooks are imported, not prepended

- Status: accepted
- Date: 2026-10-14

## Context

Several plugin hooks override hookables of the Sylius core. A configuration prepended by the
bundle extension is loaded before the application's and the Sylius one, so the Sylius core hooks
would win over the plugin hooks that replace them.

## Decision

The hooks live in YAML files that the application imports through the plugin configuration file,
after the Sylius configuration.

## Consequences

- Installing the plugin means importing that file, which the Flex recipe does.
- An application can still override any plugin hook after the import.
- The hooks and their hookables are public contract.

## Related

- [0005. The plugin takes over the Sylius search form so it keeps the advanced filters](0005-search-form-taken-over-to-keep-the-advanced-filters.md)
