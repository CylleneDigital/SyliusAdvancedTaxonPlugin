# Documentation

Documentation of **cyllene-digital/sylius-advanced-taxon-plugin**. What the plugin does, in one
list: [Features](features.md). Installation in short and production notes: the root
[README](../README.md).

## Audience

| Profile | Recommended pages |
|---------|-------------------|
| Merchant (back office) | [Back-office guide](admin/user-guide.md), [Storefront](shop/storefront.md), [Conditional taxons](domain/conditional-taxons.md) |
| Sylius integrator | [Installation](integration/installation.md), [Configuration](integration/configuration.md), [Theming and Twig hooks](integration/theming.md), [Assets](integration/assets.md), [Twig functions](shop/twig-functions.md), [Public contract](architecture/public-contract.md), [Troubleshooting](integration/troubleshooting.md) |
| Ops (deployment) | [Migrations](persistence/migrations.md), [Database schema](persistence/schema.md), [Configuration: Messenger](integration/configuration.md), [Uninstall](integration/uninstall.md), [UPGRADE](../UPGRADE.md) |
| Plugin contributor | [Architecture overview](architecture/overview.md), [Code map](development/code-map.md), [Tests](testing/tests.md), [CONTRIBUTING](../CONTRIBUTING.md) |

## Table of contents

### Overview

- [Features](features.md): every feature, with the page that details it, and what the plugin does not do

### Back office

- [Back-office guide](admin/user-guide.md): the taxon form tab by tab, the channel toggle, conditions and their preview

### Storefront

- [Storefront](shop/storefront.md): taxon and universe pages, listing, filters, media, menus, URL parameters
- [Twig functions](shop/twig-functions.md): every `advanced_taxon_*` function

### Domain

- [Conditional taxons](domain/conditional-taxons.md): condition types and operators, validation, synchronization, preview, limitations

### Integration

- [Installation](integration/installation.md): requirements, Flex or manual install, entity traits, migrations, scheduling
- [Configuration](integration/configuration.md): bundle configuration, Liip Imagine filter sets, Messenger, shop product grid
- [Theming and Twig hooks](integration/theming.md): every hook the plugin adds, replaces or disables, template overrides
- [Assets](integration/assets.md): entrypoints, Stimulus controllers, icons
- [Troubleshooting](integration/troubleshooting.md): symptoms, causes, fixes
- [Uninstall](integration/uninstall.md): removing the plugin and what happens to the data

### Architecture and persistence

- [Architecture overview](architecture/overview.md): layers, main flows, principles
- [Public contract](architecture/public-contract.md): what is stable (semver major), support policy
- [Database schema](persistence/schema.md): added columns and tables
- [Migrations](persistence/migrations.md): the single migration, portability, drift check

### Development

- [Code map](development/code-map.md): where each part lives, wiring, front-end conventions
- [Tests](testing/tests.md): suites, test database, Behat
