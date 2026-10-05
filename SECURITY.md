# Security policy

This plugin stores taxon customization, media texts and links, and uploaded pictograms from the back
office, renders them on every taxon page and in the shop menu, and exposes an admin endpoint that
queries the catalog. A flaw here can run stored script in visitors' browsers, store an unexpected
file, or read products through that endpoint, so please report it privately.

## Reporting a vulnerability

**Do not open a public issue.** Use either of the two private channels:

- [GitHub security advisory](https://github.com/CylleneDigital/SyliusAdvancedTaxonPlugin/security/advisories/new)
  ("Report a vulnerability")
- email to sylius@groupe-cyllene.com

Please include the plugin version, the Sylius and PHP versions, the database, and the steps to
reproduce. **Leave out customer data and pre-production URLs.**

## Response time

First response within **5 working days**. We keep you posted on the analysis, then on the fix and its
release date.

## Supported versions

| Version | Support |
|---|---|
| `1.x` | Bug and security fixes |

The supported Sylius, Symfony, PHP and database versions are listed in
[`docs/architecture/public-contract.md`](docs/architecture/public-contract.md#support-policy).

## Disclosure

Coordinated disclosure: the fix is released first, then the advisory. We are happy to credit the
reporter, unless they ask otherwise.
