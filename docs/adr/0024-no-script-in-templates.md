# 0024. No script in the templates, behaviour in Stimulus controllers

- Status: accepted
- Date: 2026-10-14

## Context

Inline scripts are hard to test, cannot be overridden by a theme without copying the template,
and conflict with a strict Content Security Policy.

## Decision

The templates hold no script. A behaviour is a Stimulus controller of the plugin entrypoints,
under a prefix of its own; the texts it shows come from the translations, passed as Stimulus
values.

## Consequences

- The shop and the back office have to build the plugin entrypoints.
- A theme changes a behaviour by replacing or extending its controller.

## Related

- [0021. Template icons are local](0021-template-icons-are-local.md)
