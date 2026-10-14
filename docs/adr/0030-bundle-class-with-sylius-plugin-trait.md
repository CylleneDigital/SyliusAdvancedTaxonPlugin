# 0030. A `Bundle` class with `SyliusPluginTrait`, not `AbstractBundle`

- Status: accepted
- Date: 2026-10-14

## Context

Symfony's `AbstractBundle` puts the configuration and the extension in the bundle class. A Sylius
plugin uses `SyliusPluginTrait`, which redefines `getContainerExtension()`.

## Decision

The bundle is a plain `Bundle` with `SyliusPluginTrait` and a classic `Extension` class, like the
other Sylius plugins.

## Consequences

- The extension loads the services and prepends what the plugin configures in other bundles
  (Doctrine mapping, migrations, Liip Imagine, UX Icons).
- The bundle class and its namespace are part of the public surface.

## Related

- [0028. Services are declared explicitly, without autowiring](0028-explicit-services-no-autowiring.md)
