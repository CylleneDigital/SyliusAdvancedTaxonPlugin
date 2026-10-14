# 0024. No script in the templates, behaviour in Stimulus controllers

- Status: accepted
- Date: 2026-10-14

## Context

Inline scripts are hard to test, cannot be overridden by a theme without copying the template, and
conflict with a strict script Content Security Policy.

## Decision

The templates hold no script. A behaviour lives in the plugin entrypoints, as a Stimulus controller
under a prefix of its own; the texts it shows come from the translations, passed as Stimulus values.

## Consequences

- The shop and the back office have to build the plugin entrypoints.
- A theme changes a behaviour by replacing or extending its controller.

## Related

- [0027. Translation keys keep their namespaces](0027-translation-keys-keep-their-namespaces.md)
